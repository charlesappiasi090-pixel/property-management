<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `journal_entries` – a simple general‑ledger for the business.
 *
 * Each row is a single debit or credit entry.  The `amount` is stored as a
 * decimal string (matching the Money class convention).  Entries are not
 * morph‑attached to other models in Phase 9 – they are business‑scoped only,
 * which keeps the schema simple while still allowing arbitrary posting (e.g.
 * rent receipts, expense accruals, owner draws, etc.).
 *
 * The `transaction_type` enum restricts entries to `debit` or `credit`.
 *
 * Safety: `BelongsToBusiness` supplies the global `business_id` scope, and
 * `JournalPolicy` enforces 404 for cross‑tenant records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->morphs('business'); // business_id + business_type (usually App\Models\Business)

            $table->string('description');

            $table->decimal('amount', 15, 2);

            $table->enum('transaction_type', ['debit', 'credit']);

            $table->string('reference_type')->nullable(); // e.g. App\Models\Property, App\Models\Expense

            $table->unsignedInteger('reference_id')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['transaction_type', 'created_at']);

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};