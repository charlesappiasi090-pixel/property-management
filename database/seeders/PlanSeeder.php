<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the three SaaS plans from Phase 13.
 *
 * Because plans are pure DATA (see the `plans` migration), this file is the
 * only place the word "Starter" appears in code. A price change, a fourth
 * tier, or a regional variant is a row update or an insert, never a
 * deployment.
 *
 * The numbers below are illustrative defaults chosen to be internally
 * consistent (each tier is a strict superset of the one below it). They are
 * NOT live pricing and must be set to real figures before any payment gateway
 * is connected in Phase 13.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $currency = config('propertyhub.currency', 'USD');

        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'tagline' => 'For a landlord with a handful of units.',
                'description' => 'Everything you need to run a small portfolio, including rent collection and maintenance requests.',
                'price' => '19.00',
                'currency' => $currency,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'max_properties' => 1,
                'max_units' => 5,
                'max_staff' => 1,
                'max_documents' => 50,
                'features' => [
                    'rent_collection' => true,
                    'maintenance' => true,
                    'tenant_portal' => true,
                    'expenses' => true,
                    'documents' => true,
                    'financial_reports' => false,
                    'occupancy_reports' => false,
                    'api_access' => false,
                ],
                'sort_order' => 1,
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'tagline' => 'For professional property managers.',
                'description' => 'Unlimited properties, a full staff team, and the financial reporting you need to run a portfolio.',
                'price' => '49.00',
                'currency' => $currency,
                'billing_interval' => 'monthly',
                'trial_days' => 14,
                'max_properties' => 10,
                'max_units' => 100,
                'max_staff' => 5,
                'max_documents' => 1000,
                'features' => [
                    'rent_collection' => true,
                    'maintenance' => true,
                    'tenant_portal' => true,
                    'expenses' => true,
                    'documents' => true,
                    'financial_reports' => true,
                    'occupancy_reports' => true,
                    'api_access' => false,
                ],
                'sort_order' => 2,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'tagline' => 'For agencies and large portfolios.',
                'description' => 'Unlimited everything, priority support, and the reporting and API access an agency needs.',
                'price' => '149.00',
                'currency' => $currency,
                'billing_interval' => 'monthly',
                'trial_days' => 30,
                'max_properties' => null,   // null = unlimited
                'max_units' => null,
                'max_staff' => null,
                'max_documents' => null,
                'features' => [
                    'rent_collection' => true,
                    'maintenance' => true,
                    'tenant_portal' => true,
                    'expenses' => true,
                    'documents' => true,
                    'financial_reports' => true,
                    'occupancy_reports' => true,
                    'api_access' => true,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                [...$plan, 'is_active' => true, 'is_public' => true],
            );
        }
    }
}
