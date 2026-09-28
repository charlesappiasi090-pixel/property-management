<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rent_rolls` – a snapshot of the rent roll at a point in time.
 *
 * A rent roll is a denormalised snapshot that captures the state of all
 * leases at a given date.  It is materialised (created) on demand rather
 * than stored continuously, because lease statuses (open/terminated),
 * rents, and tenants change over time.
 *
 * The roll captures:
 *   - property
 *   - unit (if any)
 *   - tenant
 *   - lease start/end dates
 *   - monthly rent
 *   - status (active/terminated)
 *
 * WHY A SNAPSHOT RATHER THAN A LIVE QUERY
 * --------------------------------------
 * A live query (`Lease::where('status', 'active')->...`) can be slow when
 * the lease table grows to thousands of rows, and the result changes every
 * time a lease is signed or terminated.  A snapshotted rent roll gives the
 * UI a stable, fast‑reading dataset that can be exported, charted, or
 * compared year‑over‑year without race conditions.
 *
 * The roll is created by the `ReportService` (not part of this PR) and
 * stored in a separate `rent_rolls` table with a `snapshot_at` timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_rolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('lease_id')
                ->constrained('leases')
                ->cascadeOnDelete();

            $table->date('snapshot_at');

            $table->string('tenant_name');

            $table->string('unit_label')->nullable();

            $table->date('lease_start_date');

            $table->date('lease_end_date')->nullable();

            $table->decimal('monthly_rent', 15, 2);

            $table->enum('lease_status', ['active', 'terminated'])
                ->default('active');

            $table->timestamps();

            $table->softDeletes();

            $table->index(['property_id', 'snapshot_at'],
                'rent_rolls_property_snapshot_index');

            $table->index(['tenant_id', 'snapshot_at'],
                'rent_rolls_tenant_snapshot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_rolls');
    }
};