<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `properties` — a building or parcel a business manages.
 *
 * A property is the top of the physical hierarchy:
 *
 *     business -> property -> unit -> (lease, charges, maintenance, documents)
 *
 * WHY A PROPERTY IS NOT A "BUILDING"
 * ---------------------------------
 * A separate `buildings` concept exists in the permission catalogue
 * (`buildings.create` and friends) for a multi-block site — a 40-unit
 * apartment complex where each block is tracked separately. That is a Phase 2
 * *refinement* of this table, not a different aggregate: a property with
 * buildings still has a single address, a single owner of record, and one
 * place where units are counted against the plan. Splitting it now would mean
 * every query has to answer "property or building?" for a case that does not
 * exist yet.
 *
 * WHY SOFT DELETES
 * ----------------
 * A property is financial history. Deleting one that has had leases and
 * payments would orphan every record beneath it and quietly rewrite a landlord's
 * books. The row is retained and hidden; `units` are soft-deleted alongside it
 * (see `App\Services\Portfolio\PropertyCatalog::deleteProperty()`), because a
 * unit whose parent no longer appears in the portfolio is a support call.
 *
 * `business_id` is NOT NULL and indexed. It is what the `BelongsToBusiness`
 * global scope filters on, and making it nullable would mean the scope had to
 * special-case null — which is exactly where cross-tenant leaks come from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name', 180);

            // Values are owned by App\Enums\PropertyType. Kept as a plain
            // string column so the enum can gain a case without a migration.
            $table->string('property_type', 24)->default('other');

            $table->text('description')->nullable();

            // Postal address. `country` is ISO 3166-1 alpha-2. Same shape as
            // `businesses`, so one address formatter and one map link serve
            // both.
            $table->string('address_line1', 180)->nullable();
            $table->string('address_line2', 180)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 24)->nullable();
            $table->char('country', 2)->nullable();

            // The beneficial owner of the asset, which is frequently NOT the
            // business. An agency manages a block for a landlord; the lease and
            // the accounting are the agency's, but the asset belongs to someone
            // else and that has to be recorded somewhere.
            $table->string('owner_name', 180)->nullable();
            $table->string('owner_email', 190)->nullable();
            $table->string('owner_phone', 40)->nullable();

            $table->unsignedSmallInteger('year_built')->nullable();

            // Retired from the active portfolio but kept, e.g. sold or
            // converted to storage. Distinct from a soft delete, which means
            // "this record should not exist"; `is_active = false` means "this
            // record is real and still relevant, just not being let".
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // The index the portfolio list uses: one business, not archived,
            // grouped by type.
            $table->index(
                ['business_id', 'property_type', 'is_active'],
                'properties_business_type_active_index'
            );

            // Name lookups are per-business. Deliberately NOT unique: a
            // landlord may legitimately have two properties called "Flat 1" in
            // different blocks, and uniqueness is enforced in the form request
            // (which can explain itself) rather than as a raw database error.
            $table->index(['business_id', 'name'], 'properties_business_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
