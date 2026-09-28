<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of subscription plans (Starter / Professional / Business).
 *
 * PLANS ARE CATALOGUE ROWS, NOT CODE. Nothing in the application branches on
 * a plan name; every quota is a nullable integer column that the gatekeeper
 * (`App\Services\Billing\PlanQuota`) reads, and every capability is a key in
 * the `features` JSON. That means a fourth plan, or a price change, is an
 * INSERT/UPDATE rather than a deployment.
 *
 * A NULL quota means "unlimited", which is why the columns are nullable
 * rather than defaulting to 0.
 *
 * Billing is not wired up in Phase 1 — see Phase 13. The schema is provider
 * agnostic: `provider`/`provider_*` columns stay NULL until a gateway is
 * chosen, and `subscriptions.status` is the single source of truth that
 * `EnsureSubscriptionIsActive` middleware reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            // Stable machine identifier referenced by seeders, fixtures and
            // the pricing page. Never change it once customers are on it.
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('tagline', 255)->nullable();
            $table->text('description')->nullable();

            // Money is DECIMAL(15,2), never float.
            $table->decimal('price', 15, 2)->default(0);
            $table->char('currency', 3)->default('USD');

            $table->string('billing_interval', 20)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(14);

            // Quotas. NULL = unlimited.
            $table->unsignedInteger('max_properties')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_documents')->nullable();

            // Capability flags, e.g. {"reports":true,"api_access":false}.
            // Read with Plan::can() so feature checks live in one place.
            $table->json('features')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'plans_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
