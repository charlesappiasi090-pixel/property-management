<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tenants` – a business's external tenant / landlord record.
 *
 * A tenant is the person or entity that holds a lease or rental agreement.
 * It is *not* a Spatie team / user – it is an external party the business
 * manages data for.  The global `BelongsToBusiness` scope filters on
 * `business_id` so a user can only ever see tenants belonging to their own
 * active business.
 *
 * WHY A SEPARATE TABLE AND NOT PART OF THE `users` TABLE
 * -----------------------------------------------------
 * Tenants have their own onboarding flow, their own contact fields, and
 * may be referenced by many leases across many properties.  Modelling them
 * as first‑class records makes the later phases (leases, payments, portal)
 * much simpler than trying to squeeze them into the authentication system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name', 180);

            // The individual named on the lease / rental agreement.
            $table->string('contact_name', 180)->nullable();

            // Public contact fields – used for letters, receipts, directory.
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();

            // Correspondence address (optional – some tenants are companies
            // with a registered office elsewhere).
            $table->string('address_line1', 180)->nullable();
            $table->string('address_line2', 180)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 24)->nullable();
            $table->char('country', 2)->nullable();

            // Soft‑delete: a tenant that once had leases must remain visible
            // in reports even after all its leases are terminated.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // The index the tenant list uses.
            $table->index('business_id', 'tenants_business_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};