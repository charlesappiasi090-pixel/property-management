<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The default seed for a working local environment.
 *
 * ORDER MATTERS
 * -------------
 *   1. RolePermissionSeeder  — creates the permissions and the global role
 *                              templates. Nothing else can grant access until
 *                              these exist.
 *   2. PlanSeeder             — the purchasable catalogue. Onboarding
 *                              validates against it, so a fresh signup fails
 *                              without this.
 *
 * Deliberately NOT seeded:
 *   - a demo business, staff or properties. Those arrive in Phase 2+, and
 *     inventing them here would mean the first thing anyone sees is data that
 *     has no UI behind it. The real flow is: register -> onboarding -> the
 *     empty dashboard, which is the honest first-run experience.
 *   - a super admin. Escalating privileges is a deliberate, manual action.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
        ]);
    }
}
