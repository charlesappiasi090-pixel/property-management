<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\PermissionName;
use App\Support\Tenancy\BusinessContext;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who can sign in to PropertyHub.
 *
 * IMPORTANT: a `User` is NOT a member of exactly one business. A property
 * manager may work for several landlords, so the user <-> business link is a
 * many-to-many membership (`business_user`) and all role/permission state is
 * scoped per business by spatie's teams feature.
 *
 * `MustVerifyEmail` is intentionally NOT implemented yet. It is switched on
 * in the signup policy step of Phase 1, once the business-registration flow
 * exists — see the commented `implements` line below.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'job_title',
        'locale',
        'timezone',
        'preferred_business_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Businesses this user belongs to, with the membership flags.
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->withPivot(['job_title', 'is_active', 'is_default', 'joined_at', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Just the business memberships, for the switcher. Eager loaded once per
     * request by the ResolveActiveBusiness middleware.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Convenience pointer used to remember where to land after login. It is a
     * hint only: middleware re-validates it against `businesses()` on every
     * request, so tampering with the column cannot grant access.
     */
    public function preferredBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'preferred_business_id');
    }

    /* ------------------------------------------------------------------ */
    /* Tenancy helpers                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Is the user a member of this business, with membership active?
     */
    public function belongsToBusiness(int $businessId): bool
    {
        return $this->businesses()
            ->where('businesses.id', $businessId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Businesses this user can actually switch into, for the UI switcher.
     */
    public function availableBusinesses()
    {
        return $this->businesses()
            ->wherePivot('is_active', true)
            ->orderByDesc('business_user.is_default')
            ->orderBy('businesses.name');
    }

    /**
     * The business to open on login, in priority order:
     *   1. explicit session choice (handled by middleware)
     *   2. the `preferred_business_id` pointer, if still a valid membership
     *   3. the first active membership
     */
    public function resolveDefaultBusiness(): ?Business
    {
        if ($this->preferred_business_id !== null && $this->belongsToBusiness($this->preferred_business_id)) {
            return $this->preferredBusiness()->first();
        }

        return $this->businesses()
            ->wherePivot('is_active', true)
            ->orderByDesc('business_user.is_default')
            ->orderBy('businesses.name')
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /* Role helpers (always within the active business)                    */
    /* ------------------------------------------------------------------ */

    /**
     * Does the user hold this role in a business?
     *
     * Pass `$business` to ask about a SPECIFIC tenant. Omit it to mean "the
     * active one", which is what the request lifecycle wants — the middleware
     * has already resolved the tenant and set the Spatie team id.
     *
     * WHY THE EXPLICIT OVERLOAD EXISTS
     * --------------------------------
     * `hasRole()` is team-scoped through a piece of global mutable state
     * (`getPermissionsTeamId()`), so with no argument this method silently
     * answers a question about whichever business happened to be set last.
     * That is fine inside a request and actively dangerous anywhere else: a
     * console command, a queued job, or a test that forgot to re-arm the
     * context will get a confident `false` (or worse, a confident `true`)
     * about the wrong landlord.
     *
     * Naming the business makes the question answerable without relying on
     * ambient state, which is the only reliable way to assert on roles.
     */
    public function hasRoleInBusiness(Role|string $role, Business|int|null $business = null): bool
    {
        $name = $role instanceof Role ? $role->value : $role;

        $businessId = $this->resolveBusinessId($business);

        // Fail CLOSED. No tenant resolved means the answer is "no", never
        // "assume the last one" — the same rule the global scope follows.
        if ($businessId === null) {
            return false;
        }

        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $this->getMorphClass())
            ->where('mhr.model_id', $this->getKey())
            ->where('mhr.business_id', $businessId)
            ->where('r.name', $name)
            ->where('r.guard_name', $this->getDefaultGuardName())
            ->exists();
    }

    /**
     * The business whose role/permission lookups currently apply.
     *
     * Prefers the Spatie team id because that is the value the permission
     * system actually used for the checks being asked about; falls back to the
     * tenancy context, which is what sets it.
     */
    protected function currentBusinessId(): ?int
    {
        $teamId = getPermissionsTeamId();

        if ($teamId !== null) {
            return (int) $teamId;
        }

        return app(BusinessContext::class)->id();
    }

    /**
     * Is this user a back-office user (i.e. NOT a tenant-only account)?
     *
     * This is the check the routing layer uses to decide between the staff
     * dashboard and the tenant portal. Note the direction of the test: it asks
     * whether the user holds a back-office role, NOT whether they hold the
     * tenant role. Those are not the same question.
     *
     * A user who is a tenant in one business and staff in another passes
     * correctly, and so does a user who holds BOTH roles in the same business.
     * Testing "is a tenant" instead would bounce the second group out of a
     * back office they are entitled to.
     *
     * A role name this codebase does not recognise (a business has added its
     * own) resolves to "not a known back-office role" and so does not grant
     * access. Fails closed, deliberately.
     */
    public function isBackOfficeUser(Business|int|null $business = null): bool
    {
        $businessId = $this->resolveBusinessId($business);

        if ($businessId === null) {
            return false;
        }

        return $this->roleNamesIn($businessId)
            ->contains(function (string $name): bool {
                // `tryFrom`, not `from`: a business may add its own custom
                // role later, and an unknown role name must degrade to "not a
                // known back-office role" rather than throwing a ValueError
                // from deep inside a view or a redirect.
                return Role::tryFrom($name)?->usesBackOffice() ?? false;
            });
    }

    public function isOwner(Business|int|null $business = null): bool
    {
        return $this->hasRoleInBusiness(Role::OWNER, $business);
    }

    /**
     * Does this user hold a permission IN A SPECIFIC BUSINESS?
     *
     * The business-explicit counterpart to Laravel's `can()`, for the same
     * reason `hasRoleInBusiness()` exists: `can()` resolves through spatie's
     * `roles` relation, which is scoped by a piece of global mutable state
     * (`getPermissionsTeamId()`). Inside a request that happens to be correct,
     * because `ResolveActiveBusiness` validated the session and set the team
     * before anything ran. Anywhere else - a queue job, a console command, a
     * policy invoked directly in a test - it silently answers about whichever
     * business was set last.
     *
     * `BusinessPolicy` is the case that matters. Each of its abilities checks
     * `belongsToBusiness($business)` by name but then called `can()` without
     * one, so the membership test was pinned to a specific landlord while the
     * permission test beside it was not. Those two halves have to agree, or the
     * policy enforces "a member of THIS business who has the permission in
     * WHATEVER business was last active".
     *
     * Written as explicit queries rather than by swapping the team id and
     * calling `can()`, because temporarily mutating global state to answer a
     * question is how the ambiguity got here in the first place. It also means
     * this call has no side effects at all, so it is safe inside a loop and
     * safe to interleave with anything else that reads the team id.
     *
     * The queries mirror spatie's team semantics exactly: the pivot's
     * `business_id` must equal the business being asked about, while the ROLE
     * row may be either that business's own row or a shared template
     * (`business_id IS NULL`). Filtering the role side as well would silently
     * drop every permission granted through a template role.
     *
     * Fails closed when no business is resolved.
     */
    public function canInBusiness(PermissionName|string $permission, Business|int|null $business = null): bool
    {
        $businessId = $this->resolveBusinessId($business);

        if ($businessId === null) {
            return false;
        }

        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        $guard = $this->getDefaultGuardName();
        $morph = $this->getMorphClass();
        $key = $this->getKey();

        $viaRole = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'r.id')
            ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
            ->where('mhr.model_type', $morph)
            ->where('mhr.model_id', $key)
            ->where('mhr.business_id', $businessId)
            ->where('r.guard_name', $guard)
            ->where('p.guard_name', $guard)
            ->where('p.name', $name)
            ->exists();

        if ($viaRole) {
            return true;
        }

        // Directly-granted permissions, which never pass through a role. Rare
        // here, but spatie honours them and so must this or an owner who
        // delegates a single permission would appear not to have it.
        return DB::table('model_has_permissions as mhp')
            ->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
            ->where('mhp.model_type', $morph)
            ->where('mhp.model_id', $key)
            ->where('mhp.business_id', $businessId)
            ->where('p.guard_name', $guard)
            ->where('p.name', $name)
            ->exists();
    }

    /**
     * Does this user hold a role in a specific business? Business-explicit
     * counterpart to `hasRole()`, for the reason `canInBusiness()` exists.
     */
    public function isTenant(Business|int|null $business = null): bool
    {
        return $this->hasRoleInBusiness(Role::TENANT, $business);
    }

    /**
     * The role names this user holds in one specific business.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function roleNamesIn(int $businessId): \Illuminate\Support\Collection
    {
        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $this->getMorphClass())
            ->where('mhr.model_id', $this->getKey())
            ->where('mhr.business_id', $businessId)
            ->where('r.guard_name', $this->getDefaultGuardName())
            ->pluck('r.name');
    }

    /**
     * Normalise `Business|id|null` to a business id, defaulting to whichever
     * tenant is currently in effect.
     */
    protected function resolveBusinessId(Business|int|null $business): ?int
    {
        if ($business instanceof Business) {
            return $business->getKey();
        }

        if (is_int($business)) {
            return $business;
        }

        return $this->currentBusinessId();
    }

    /**
     * A platform operator, not a tenant role. Grants cross-tenant visibility
     * and is always audit-logged as `is_super_admin_action`.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Can this account sign in at all?
     */
    public function canAuthenticate(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    /**
     * Display name for tables and receipts.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(implode('', array_map(
            static fn (string $part): string => mb_substr($part, 0, 1),
            array_slice($parts, 0, 2)
        )));
    }

    /* ------------------------------------------------------------------ */
    /* Query scopes                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
