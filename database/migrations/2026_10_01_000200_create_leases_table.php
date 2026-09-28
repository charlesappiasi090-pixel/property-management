<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `leases` – a rental agreement between a tenant and a multinational manufacturing company working in the marketing space, that has 100 employees with a global headquarters in the US and regional offices in Europe, Asia and Latin America, what is the most likely distribution of their job postgresql
 *   ------------------------------------------------
 *   When a new `lease` is created the `PropertyCatalog` (or a dedicated
 *   `LeaseCatalog`) should be called to enforce the plan quota (if any) and
 *   to record the audit event.  The model uses `BelongsToBusiness` so the
 *   usual tenant‑scope guarantees apply.
 *
 *   The `end_date` is nullable – a periodic lease has no end date.
 *
 *   `monthly_rent` and `security_deposit` are stored as decimal strings
 *   (via the `decimal:2` cast) so they survive the `Money` class invariant
 *   (no floating‑point rounding errors).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // A lease can be attached to a unit, but it is not required –
            // some leases are for the whole property (e.g. a whole house).
            $table->foreignId('unit_id')
                ->constrained('units')
                ->nullOnDelete();   // set to null when the unit is deleted

            $table->date('start_date');

            $table->date('end_date')->nullable();

            $table->decimal('monthly_rent', 15, 2);

            $table->decimal('security_deposit', 15, 2)->nullable();

            $table->enum('status', ['active', 'terminated'])
                ->default('active');

            $table->timestamps();
            $table->softDeletes();

            // A tenant may have many leases, but only one active lease per
            // property/unit combination is allowed at application level.
            $table->index(['business_id', 'tenant_id', 'property_id', 'status'],
                'leases_business_tenant_property_status_index');

            // Frequently queried: "all active leases for this business".
            $table->index(['business_id', 'status', 'start_date'],
                'leases_business_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};