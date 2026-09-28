<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `businesses` is the ROOT of the multi-tenant hierarchy.
 *
 * Every domain record created from Phase 2 onwards (properties, buildings,
 * units, tenants, leases, rent_payments, expenses, maintenance_requests,
 * documents, ...) carries a non-null `business_id` foreign key pointing at
 * this table. Those keys are what `App\Support\Tenancy\BusinessContext` and
 * the `BelongsToBusiness` trait use to guarantee a business can never read
 * or write another business' rows.
 *
 * A `user` on the other hand may belong to MANY businesses (a property
 * manager working for several landlords), so ownership is modelled as a
 * many-to-many membership in `business_user` rather than a column here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();

            $table->string('name', 180);
            $table->string('slug', 120)->unique();

            // Legal / fiscal details. Optional because a sole trader may not
            // have a registered entity, but required before a receipt can be
            // issued for a real (non-trial) business.
            $table->string('legal_name', 180)->nullable();
            $table->string('tax_id', 60)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();

            // Postal address. `country` is ISO 3166-1 alpha-2.
            $table->string('address_line1', 180)->nullable();
            $table->string('address_line2', 180)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 24)->nullable();
            $table->char('country', 2)->nullable();

            // Per-tenant presentation + accounting defaults. Falling back to
            // config('propertyhub.*') keeps a fresh signup usable immediately.
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 12)->default('en');
            $table->char('currency', 3)->default('USD');

            // Stored on the PRIVATE disk, never in public/.
            $table->string('logo_path', 255)->nullable();

            $table->string('status', 24)->default('trialing');

            // Trial bookkeeping. `trial_ends_at` is authoritative; the
            // `status` column is a denormalised label the middleware reads
            // so it does not have to hit `subscriptions` on every request.
            $table->timestamp('trial_ends_at')->nullable();

            // Escape hatch for plan-gated features (e.g. per-plan unit caps,
            // feature flags) that do not warrant their own table.
            $table->json('settings')->nullable();

            $table->timestamps();

            // Soft deletes: a business is financial data. Hard-deleting one
            // would orphan every property, lease and payment beneath it, so
            // the row is retained and hidden instead.
            $table->softDeletes();

            $table->index('status', 'businesses_status_index');
            $table->index('trial_ends_at', 'businesses_trial_ends_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
