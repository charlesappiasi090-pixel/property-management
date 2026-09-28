<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `receipts` – a receipt issued for a payment.
 *
 * A receipt is the document given to the tenant as proof of payment.  It
 * carries the same financial data as the payment it refers to, but is a
 * separate record so that the original payment can be modified (e.g. a
 * correction of the amount) without invalidating the historical receipt.
 *
 * WHY A SEPARATE TABLE FROM `payments`
 * ------------------------------------
 * Receipts are primarily a *presentation* concern – they are what prints
 * on paper, what PDFs are emailed, and what the UI displays as "receipt
 * #123".  Payments are the *accounting* concern – they are the records
 * that the ledger aggregates.  Separating them means:
 *   - A payment can be edited or voided without invalidating the PDF
     receipt the tenant already printed.
 *   - The receipt can be re‑generated at any time from the original
     payment data, so the office never loses the "as‑printed" version.
 *   - The UI can show a receipt number that is independent of the
     payment’s auto‑incrementing ID.
 *
 * Receipts also carry an `issued_at` timestamp and an `issued_by` reference
 * (the user who issued it), which are not needed on the payment itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);

            $table->string('receipt_number', 50)->unique();

            $table->date('issued_at');

            $table->string('issued_by', 190)->nullable();

            $table->timestamps();

            $table->softDeletes();

            // Receipts are rarely filtered, but the unique receipt number
            // needs a quick lookup.
            $table->index(['receipt_number'], 'receipts_number_unique');
            $table->index(['business_id'], 'receipts_business_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};