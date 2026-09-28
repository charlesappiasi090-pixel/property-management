<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the `permissions` catalogue and the five role definitions.
 *
 * PERMISSIONS ARE GLOBAL, ROLES ARE PER-BUSINESS
 * ---------------------------------------------
 * `permissions` rows have no `business_id` (see the migration): "what it means
 * to be able to record a payment" is the same everywhere. `roles` DO carry
 * `business_id`, so `property_manager` for landlord A and for landlord B are
 * distinct rows that may diverge over time.
 *
 * That asymmetry is deliberate. A permission added in a later release must
 * reach every existing tenant, so permissions must be global. Two customers
 * negotiating different access for their staff must not affect each other, so
 * roles must not be global.
 *
 * The role -> permission matrix itself lives on `App\Enums\Role::permissions()`
 * so that `BusinessMembershipService::ensureRoleExists()` and this seeder can
 * never disagree about what a role grants.
 *
 * This seeder is IDEMPOTENT — safe to run on every deploy with
 * `php artisan db:seed --class=RolePermissionSeeder`. It retro-fits newly
 * added permissions onto existing roles.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermissions();
        $this->createTemplateRoles();
        $this->syncBusinessRoles();
    }

    /**
     * Every permission in the catalogue.
     */
    protected function createPermissions(): void
    {
        foreach (PermissionName::values() as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * Seed role definitions with a NULL `business_id`.
     *
     * `business_id` is nullable in the schema precisely so the catalogue can be
     * seeded globally. `BusinessMembershipService::ensureRoleExists()` then
     * creates a concrete per-business copy carrying the same permission set.
     */
    protected function createTemplateRoles(): void
    {
        foreach (Role::cases() as $role) {
            $template = SpatieRole::query()->firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
                'business_id' => null,
            ]);

            $template->syncPermissions($role->permissions());
        }
    }

    /**
     * Reconcile every per-business role with the matrix, picking up
     * permissions added since that business was created.
     */
    protected function syncBusinessRoles(): void
    {
        $roles = SpatieRole::query()->whereNotNull('business_id')->get();

        foreach ($roles as $role) {
            $definition = Role::tryFrom($role->name);

            // A role name we no longer recognise (e.g. a custom role created
            // by an earlier version) is left untouched rather than stripped.
            if ($definition === null) {
                continue;
            }

            $current = $role->permissions->pluck('name')->sort()->values()->all();
            $target = collect($definition->permissions())->sort()->values()->all();

            // Only write when the set has actually drifted, so this stays cheap
            // and does not churn `updated_at` on every deploy.
            if ($current !== $target) {
                $role->syncPermissions($definition->permissions());
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
