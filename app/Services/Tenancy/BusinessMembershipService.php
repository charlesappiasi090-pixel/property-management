<?php

namespace App\Services\Tenancy;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role as SpatieRole;
use RuntimeException;

/**
 * Owns the invariant that membership and role assignment never disagree.
 *
 * THE INVARIANT
 * -------------
 * For every (business, user) pair, exactly one of these is true:
 *   a) a row exists in `business_user` AND at least one role is attached in
 *      `model_has_roles` for that business, or
 *   b) neither exists.
 *
 * A membership with no role is a bug: the person can sign in and see the
 * business in their switcher but every permission check fails, which reads to
 * the user as "my account is broken". A role with no membership is worse — a
 * stale grant that outlives the person's access to the tenant.
 *
 * Every mutation therefore goes through this service, which writes both tables
 * inside one transaction.
 */
class BusinessMembershipService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * Attach a person to a business with a role, creating the tenant's role
     * row if it does not exist yet.
     *
     * Idempotent: calling it again with the same role is a no-op rather than a
     * duplicate, so the signup flow and the admin invite flow can share it.
     *
     * IT WILL NOT INVENT AN OWNER
     * ---------------------------
     * The first member of a business cannot be attached as anything other than
     * OWNER — a business with no owner is permanently unadministrable, since
     * only an owner may grant ownership. Rather than silently rewriting the
     * caller's requested role to `owner` (which previously happened here, and
     * meant `attach($b, $u, Role::TENANT)` handed a brand-new tenant the
     * owner's full permission set including billing and staff management), it
     * now throws. `createBusinessWithOwner()` is the supported way to make a
     * business, and it passes OWNER explicitly.
     *
     * @throws RuntimeException when the business would be left without an owner
     */
    public function attach(Business $business, User $user, Role $role, array $attributes = []): User
    {
        return DB::transaction(function () use ($business, $user, $role, $attributes) {
            $isFirstMember = ! $business->members()->exists();

            if ($isFirstMember && $role !== Role::OWNER) {
                throw new RuntimeException(sprintf(
                    'Cannot add the first member of [%s] as %s: a business with no owner cannot be administered. '
                    .'Use createBusinessWithOwner() to create a business, or add the owner first.',
                    $business->name,
                    $role->label(),
                ));
            }

            $roleRow = $this->ensureRoleExists($business, $role);

            // The spatie team id must match the business whose role we are
            // about to create/attach, otherwise the role lands in the wrong
            // tenant's namespace.
            setPermissionsTeamId($business->getKey());

            if (! $business->members()->where('users.id', $user->getKey())->exists()) {
                $business->members()->attach($user->getKey(), [
                    'job_title' => $attributes['job_title'] ?? $role->label(),
                    'is_active' => $attributes['is_active'] ?? true,
                    'is_default' => $attributes['is_default'] ?? false,
                    'joined_at' => now(),
                    'invited_by_user_id' => $attributes['invited_by_user_id'] ?? auth()->id(),
                ]);
            }

            /*
             * Assign by ROLE ID, never by name.
             *
             * `assignRole('owner')` resolves the name through
             * `Role::findByName()`, and with teams enabled that lookup is
             * `where(business_id IS NULL OR business_id = <current team>)`. The
             * global template role seeded by `RolePermissionSeeder` has
             * `business_id = NULL`, so it satisfies that predicate and — being
             * lower id — wins. The pivot then pointed at the shared template row
             * instead of the business's own role.
             *
             * That is a tenant-isolation defect, not a cosmetic one: every
             * tenant's owner shared one role row, and a permission edit on the
             * template would leak across every landlord. It also broke
             * `Business::owners()`, which pins `roles.business_id` to the
             * business and so matched nothing.
             *
             * Passing the id is unambiguous: there is exactly one row to mean.
             */
            if (! $this->userHasRoleRow($user, $roleRow)) {
                $user->assignRole($roleRow);
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');

            /*
             * Subject is the BUSINESS, not the user.
             *
             * `record($user, ...)` resolves `business_id` from the subject and
             * falls back to `BusinessContext` — and this is the first thing
             * that runs when a tenant is being created, before any middleware
             * has resolved one. The row was therefore written with a NULL
             * `business_id`: not shown in the new business's activity feed, and
             * attributable to nobody. The person being added is carried in the
             * context instead, and named in the description.
             */
            $this->audit->event(
                \App\Enums\AuditEvent::STAFF_INVITED->value,
                sprintf('Added %s to %s as %s', $user->name, $business->name, $role->label()),
                $business,
                [
                    'user_id' => $user->getKey(),
                    'user' => $user->name,
                    'role' => $role->value,
                ],
            );

            return $user->refresh();
        });
    }

    /**
     * Change someone's role within a business.
     *
     * Blocks a dangerous edge case: an owner demoting themselves when they are
     * the only owner would lock the business out of staff management
     * permanently, since only an owner may grant or revoke ownership.
     */
    public function changeRole(Business $business, User $user, Role $newRole): User
    {
        return DB::transaction(function () use ($business, $user, $newRole) {
            $current = $this->currentRole($business, $user);

            if ($current === $newRole) {
                return $user;
            }

            $this->assertNotOrphaningTheOwner($business, $user, $current, $newRole);

            $newRoleRow = $this->ensureRoleExists($business, $newRole);

            setPermissionsTeamId($business->getKey());

            // By id, not by name — see the note in `attach()`. Resolving
            // `'property_manager'` by name can silently reattach the global
            // template role, which would strip the tenant-scoped permission
            // set the role was created with.
            $user->syncRoles([$newRoleRow]);

            $user->unsetRelation('roles')->unsetRelation('permissions');

            $business->members()
                ->updateExistingPivot($user->getKey(), ['job_title' => $newRole->label()]);

            $this->audit->event(
                \App\Enums\AuditEvent::STAFF_ROLE_CHANGED->value,
                sprintf(
                    'Changed %s from %s to %s in %s',
                    $user->name,
                    $current?->label() ?? 'no role',
                    $newRole->label(),
                    $business->name
                ),
                $business,
                [
                    'user_id' => $user->getKey(),
                    'from' => $current?->value,
                    'to' => $newRole->value,
                    'business' => $business->name,
                ],
            );

            return $user->refresh();
        });
    }

    /**
     * Remove a person from a business entirely.
     *
     * Refuses to remove the last remaining owner — that would make the tenant
     * permanently unadministrable.
     */
    public function detach(Business $business, User $user): void
    {
        DB::transaction(function () use ($business, $user) {
            $current = $this->currentRole($business, $user);

            if ($current === Role::OWNER && $this->ownerCount($business) <= 1) {
                throw new RuntimeException(
                    'This is the only owner of the business. Promote another member to owner before removing them.'
                );
            }

            setPermissionsTeamId($business->getKey());

            // Detach the tenant's own role row by id. `removeRole('owner')`
            // would resolve the name and could remove the GLOBAL template role
            // instead, which would strip the `owner` definition from every
            // tenant at once.
            $roleRow = $current === null
                ? $this->ensureRoleExists($business, Role::OWNER)
                : $this->ensureRoleExists($business, $current);

            $user->removeRole($roleRow);

            $business->members()->detach($user->getKey());

            $this->audit->event(
                \App\Enums\AuditEvent::STAFF_REMOVED->value,
                sprintf('Removed %s from %s', $user->name, $business->name),
                $business,
                [
                    'user_id' => $user->getKey(),
                    'user' => $user->name,
                    'role' => $current?->value,
                ],
            );
        });
    }

    /**
     * Create a business and make the given user its owner.
     *
     * Used by signup and by the artisan seeding helpers.
     *
     * The membership row is written by `attach()` rather than here, so there is
     * exactly one code path that creates a `business_user` row and it always
     * writes the matching role row in the same transaction. An owner who holds
     * no role is a tenant nobody can administer.
     */
    public function createBusinessWithOwner(
        User $owner,
        string $name,
        ?string $slug = null,
        array $attributes = [],
    ): Business {
        return DB::transaction(function () use ($owner, $name, $slug, $attributes) {
            $business = Business::query()->create([
                'name' => $name,
                'slug' => $slug ?: $this->uniqueSlug($name),
                ...$attributes,
            ]);

            // NOTE: this returns the refreshed `User`, not the `Business`.
            // Returning it here satisfied the `: Business` return type with a
            // User and raised a TypeError on every single signup.
            $this->attach($business, $owner, Role::OWNER, [
                'job_title' => Role::OWNER->label(),
                'is_default' => true,
            ]);

            // The owner IS the tenant, so mark them as the login pointer.
            $owner->forceFill(['preferred_business_id' => $business->getKey()])->save();

            return $business;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Introspection                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * The single role this user holds in this business, or null.
     */
    public function currentRole(Business $business, User $user): ?Role
    {
        $row = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $user->getMorphClass())
            ->where('mhr.model_id', $user->getKey())
            ->where('mhr.business_id', $business->getKey())
            ->first();

        return $row === null ? null : Role::tryFrom($row->name);
    }

    public function ownerCount(Business $business): int
    {
        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('r.name', Role::OWNER->value)
            ->where('mhr.business_id', $business->getKey())
            ->distinct()
            ->count('mhr.model_id');
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Does this user already hold THIS specific role row in the active team?
     *
     * Deliberately checks the id rather than calling `$user->hasRole($name)`.
     * `hasRole()` matches on the role's NAME, and the global template role
     * shares every name with the per-business roles. A name check would report
     * "already an owner" when the user holds the template row and silently skip
     * attaching the tenant-scoped one, which is how the isolation bug in
     * `attach()` survived in the first place.
     */
    protected function userHasRoleRow(User $user, SpatieRole $role): bool
    {
        setPermissionsTeamId($role->business_id ?? getPermissionsTeamId());

        $user->unsetRelation('roles')->load('roles');

        return $user->roles->contains($role->getKey());
    }

    /**
     * Create this business' copy of a role, with its permissions, if it does
     * not exist yet.
     *
     * spatie teams mean roles are per-tenant rows sharing a name, so the first
     * member of every business triggers a role creation. The permission set
     * comes from `Role::permissions()` — the same matrix the seeder uses — so
     * a self-service signup and a seeded tenant are never granted different
     * powers.
     */
    public function ensureRoleExists(Business $business, Role $role): SpatieRole
    {
        $existing = SpatieRole::query()
            ->where('name', $role->value)
            ->where('guard_name', 'web')
            ->where('business_id', $business->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            $created = SpatieRole::query()->create([
                'name' => $role->value,
                'guard_name' => 'web',
                'business_id' => $business->getKey(),
            ]);
        } catch (PermissionDoesNotExist) {
            // Extremely unlikely — a unique index guards the row — but if two
            // requests race, the loser simply reads the winner's row.
            return SpatieRole::query()
                ->where('name', $role->value)
                ->where('guard_name', 'web')
                ->where('business_id', $business->getKey())
                ->firstOrFail();
        }

        $created->syncPermissions($role->permissions());

        return $created->refresh();
    }

    protected function assertNotOrphaningTheOwner(
        Business $business,
        User $user,
        ?Role $current,
        Role $newRole,
    ): void {
        if ($current !== Role::OWNER) {
            return;
        }

        if ($this->ownerCount($business) > 1) {
            return;
        }

        throw new RuntimeException(
            'You are the only owner of this business. Promote another member to owner before changing your own role.'
        );
    }

    protected function uniqueSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 2;

        while (Business::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
