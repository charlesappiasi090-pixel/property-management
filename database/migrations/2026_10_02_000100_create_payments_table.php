<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payments` – a single rent payment recorded against a lease.
 *
 * A payment is the concrete financial transaction that records money received
 * from a tenant.  It is not the lease itself (that is Phase 3) but the
 * actual receipt of funds.  Every payment is linked to a lease so that the
 * ledger can reconcile "what was agreed" (lease monthly_rent) with "what was
 * received" (payment amount).
 *
 * WHY A SEPARATE TABLE FROM `leases`
 * ---------------------------------
 * The `leases` table stores the *agreement* (rent amount, dates, status).
 * The `payments` table stores the *actual receipts* (date received, method,
 * reference number, status).  Keeping them separate means:
 *   - A lease can exist without any payments having been made yet (e.g. a
 *     future‑dated lease or a lease that is still being set up).
 *   - Multiple payments can be recorded for a single lease (e.g. quarterly
 *     payments, part‑payments, or a payment plan).
 *   - Payments can be voided, refunded, or marked failed without touching
 *     the lease agreement.
 *   - The dashboard "ledger" tile shows total received vs. total agreed,
 *     which requires joining `payments.monthly_rent` with `leases.monthly_rent`.
 *
 * `payment_status` is an enum because the UI needs to distinguish between
 * "successful", "failed" and "refunded" – three states that a plain boolean
 * cannot express.
 *
 * `method` stores the payment gateway or manual method used (e.g. "cash",
 * "bank_transfer", "credit_card").  It is free‑text so that new methods can
 * be added without a migration.
 *
 * `reference` is optional – it is the check number, transaction ID or
 * receipt number the tenant or accountant would write on a physical copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lease_id')
                ->constrained('leases')
                ->cascadeOnDelete();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            $table->enum('status', ['active', 'failed', 'refunded'])
                ->default('active');

            $table->enum('method', ['cash', 'bank_transfer', 'credit_card', 'check', 'other'])
                ->nullable();

            $table->string('reference', 100)->nullable();

            $table->date('payment_date');

            $table->timestamps();

            $table->softDeletes();

            // Frequently used filters.
            $table->index(['lease_id', 'status'], 'payments_lease_status_index');
            $table->index(['business_id', 'status'], 'payments_business_status_index');
            $table->index(['payment_date'], 'payments_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};