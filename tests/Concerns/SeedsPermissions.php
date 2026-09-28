<?php

namespace Tests\Concerns;

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the permission catalogue for a test.
 *
 * WHY THIS IS NEEDED
 * ------------------
 * `RefreshDatabase` runs migrations only, never seeders. But
 * `BusinessMembershipService::ensureRoleExists()` ends in
 * `$role->syncPermissions($role->permissions())`, and Spatie throws
 * `PermissionDoesNotExist` when a name in that list has no row in
 * `permissions`. So any test that attaches a member through the service fails
 * with an error that looks like a bug in the service but is really a missing
 * fixture.
 *
 * This is a genuine deployment invariant rather than a test artefact: the
 * permission catalogue is global (no `business_id`) and is created by
 * `RolePermissionSeeder`. The service assumes it is present, which is correct
 * — inventing permissions on the fly inside a tenant-scoped service would mean
 * two businesses could disagree about what a permission means.
 *
 * Only the permission catalogue is seeded, not the business roles. Creating
 * roles here would pre-empt the code under test; `ensureRoleExists()` is
 * supposed to be what creates them.
 */
trait SeedsPermissions
{
    protected function seedPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Spatie caches the permission map in the container for the life of the
        // process. Without this, a second test in the same process can read a
        // cache built before the catalogue existed and fail non-deterministically.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
