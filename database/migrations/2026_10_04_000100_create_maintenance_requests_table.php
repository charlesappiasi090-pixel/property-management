<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `maintenance_requests` – a request to perform maintenance on a property,
 * unit, or lease.
 *
 * A maintenance request is the first step in the repair workflow: it records
 * what needs to be fixed, who reported it, and what priority it has.  The
 * actual repair work is then tracked in a separate `work_orders` table (Phase 7)
 * if the organisation desires a stricter separation between "request" and
 * "execution".
 *
 * Each request is attached to a *property* (and optionally a *lease*, *unit*,
 * or *tenant*) so that the business can see "how many maintenance requests
 * did Property X generate last year?".
 *
 * `priority` is an enum because the UI needs to distinguish between
 * `routine`, `urgent`, and `emergency` – three states that a plain boolean
 * cannot express.
 *
 * `status` is an enum because we want to distinguish `open`, `in_progress`,
 * `completed`, and `cancelled` – four states that a plain boolean cannot
 * express.
 *
 * `category` uses the `MaintenanceCategory` enum so the UI can present a
 * sensible dropdown and the data layer enforces a fixed vocabulary.
 *
 * `business_id` is denormalised from the parent property/lease/unit, exactly
 * like `payments`, `units`, and `expenses` before it.  This means the global
 * `BelongsToBusiness` scope can filter requests without a join, and quota
 * checks (if any) are a single indexed count.
 *
 * `reporter_name` and `reporter_email` store the contact details of the
 * person who filed the request – useful when the reporter is not a staff
 * member (e.g. a tenant or owner).
 *
 * `resolved_at` is nullable – a request that is still open has no resolved
 * date, while a completed request records when it was closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('lease_id')
                ->constrained('leases')
                ->nullOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->nullOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->enum('category', [
                'plumbing',
                'electrical',
                'hvac',
                'cosmetic',
                'structural',
                'other',
            ]);

            $table->enum('priority', ['routine', 'urgent', 'emergency']);

            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])
                ->default('open');

            $table->string('reporter_name', 180);

            $table->string('reporter_email', 190)->nullable();

            $table->text('description');

            $table->date('requested_at');

            $table->date('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status', 'priority'],
                'maintenance_requests_property_status_priority_index');

            $table->index(['business_id', 'status', 'priority'],
                'maintenance_requests_business_status_priority_index');

            $table->index(['lease_id'], 'maintenance_requests_lease_id_index');
            $table->index(['unit_id'], 'maintenance_requests_unit_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};