<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `units` — one lettable dwelling or space inside a property.
 *
 * THE DENORMALISED `business_id`
 * ------------------------------
 * A unit is reachable from its property, so `property_id` alone would be enough
 * for correctness. It carries `business_id` anyway, and that is deliberate:
 *
 *   - the `BelongsToBusiness` global scope filters on `business_id`, and
 *     rewriting it as a join through `properties` would put the join in front
 *     of EVERY unit query in the application, including the ones in Phase 4
 *     that filter by `property_id` and need no join at all;
 *   - a "how many units has this landlord used" quota check becomes a single
 *     indexed count instead of a count over a joined table.
 *
 * The cost is that the value can drift from the parent property's. That is
 * handled where it is written, not hoped away:
 * `App\Services\Portfolio\PropertyCatalog` stamps it from the property inside
 * the same transaction, and never accepts a caller-supplied value. Nothing
 * else in the application may create or move a unit.
 *
 * WHY `monthly_rent` IS NOT THE LEASE RENT
 * ----------------------------------------
 * This column is the *asking* rent: what the landlord would currently charge
 * for this unit. The rent actually agreed with a tenant belongs to the lease
 * in Phase 4, and it is immutable once signed. Storing the agreed figure here
 * as well would create two sources of truth that disagree the first time a
 * lease is renewed at a different price — and the wrong one would be the one
 * on the unit, which is the more visible of the two.
 *
 * Occupancy is deliberately NOT a column either. "Is this let?" is a question
 * about the leases on it, and a stored boolean is guaranteed to disagree with
 * them after the first manual correction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            // Free-text on purpose: "2B", "Unit 4", "Shop 3 (rear)", "Suite 100".
            // Forcing a numeric identifier would break the naming conventions
            // landlords actually use, and the label is what appears on leases,
            // notices and repairs.
            $table->string('label', 60);

            $table->unsignedTinyInteger('bedrooms')->nullable();

            // Decimal, not an integer: 1.5 and 2.5 baths are ordinary in
            // residential property, and rounding them to 1 or 2 is a lie the
            // user notices immediately.
            $table->decimal('bathrooms', 3, 1)->nullable();

            $table->unsignedInteger('square_feet')->nullable();
            $table->string('floor', 20)->nullable();

            // Asking rent. DECIMAL(15,2) is read with the `decimal:2` cast and
            // used through App\Support\Money\Money — never as a float.
            $table->decimal('monthly_rent', 15, 2)->nullable();

            $table->text('notes')->nullable();

            // "In the portfolio but not currently being let" — e.g. awaiting
            // refurbishment. Not an occupancy flag; see the class docblock.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['property_id', 'label'],
                'units_property_label_index'
            );

            // The quota counter's index: all units for one business.
            $table->index(['business_id', 'is_active'], 'units_business_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
