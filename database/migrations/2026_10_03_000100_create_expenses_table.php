<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `expenses` – a record of a cost incurred by a business.
 *
 * An expense is a lightweight ledger entry for things like:
 *   - Maintenance repairs
 *   - Utility bills
 *   - Office supplies
 *   - Insurance premiums (partial Phase 4 overlap)
 *   - Any other operational cost not covered by rent or deposits
 *
 * Each expense is attached to a *property* (and optionally a *lease* or *unit*)
 * so that the business can see "how much did we spend on Property X this year?".
 * The `category` field uses the `ExpenseCategory` enum so the UI can present
 * a sensible dropdown and so the data layer enforces a fixed vocabulary.
 *
 * `business_id` is denormalised from the parent property/lease/unit, exactly
 * like `payments` and `units` before it.  This means the global `BelongsToBusiness`
 * scope can filter expenses without a join, and quota checks (if any) are a
 * single indexed count.
 *
 * `status` is an enum because we want to distinguish `pending`, `approved`,
 * `reimbursed`, and `written_off` – three states that a plain boolean cannot
 * express.
 *
 * `receipt_path` stores the filesystem path or S3 key of the uploaded receipt
 * image, so the UI can display a preview without touching the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
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
                'maintenance',
                'repairs',
                'utilities',
                'office_supplies',
                'insurance',
                'other',
            ]);

            $table->decimal('amount', 15, 2);

            $table->enum('status', ['pending', 'approved', 'reimbursed', 'written_off'])
                ->default('pending');

            $table->text('description')->nullable();

            $table->string('receipt_path', 255)->nullable();

            $table->date('expense_date');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'category', 'status'],
                'expenses_property_category_status_index');

            $table->index(['business_id', 'status', 'expense_date'],
                'expenses_business_status_date_index');

            $table->index(['lease_id'], 'expenses_lease_id_index');
            $table->index(['unit_id'], 'expenses_unit_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};