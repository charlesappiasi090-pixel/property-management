{{--
    The expense form, shared by create and edit.

    SHARED RATHER THAN DUPLICATED
    ----------------------------
    The two screens differ in three lines: the heading, the button text, and
    whether `old()` should fall back to an existing record. Everything else —
    which fields exist, what they are called, which validation message each one
    produces — must be identical, or a value would become creatable and
    un-editable in the same release. That is close to impossible to diagnose
    from a bug report, so the field list lives here once.

    `old()` precedence is handled by each field component, which reads
    `old($name, $selected)`. All this partial has to supply is the record's
    value as the fallback.
--}}

@php
    $categories = collect(ExpenseCategory::cases())->mapWithKeys(
        fn (\App\Enums\ExpenseCategory $c): array => [$c->value => $c->label()]
    );
@endphp

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">The expense</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-select-field
            name="category"
            label="Category"
            :selected=$expense?->category
            :options=$categories
            hint="The type of expense. Commercial and land properties have no units, so the unit panel is hidden for them."
        />

        <x-text-field
            name="amount"
            type="text"
            label="Amount"
            :value=$expense?->amount
            required
            autocomplete="off"
            placeholder="e.g. 1250 or 1,250.00"
            hint="Asking amount. Format: number or number with thousand separator and up to two decimal places."
        />

        <x-select-field
            name="status"
            label="Status"
            :selected=$expense?->status
            :options="['pending' => 'Pending', 'approved' => 'Approved', 'reimbursed' => 'Reimbursed', 'written_off' => 'Written off']"
            required
        />

        <x-text-field
            name="description"
            label="Description"
            :value=$expense?->description
            rows="3"
            maxlength="1000"
            hint="Anything the team needs to know: vendor, purpose, notes."
        />

        <x-text-field
            name="receipt_path"
            type="text"
            label="Receipt path"
            :value=$expense?->receipt_path
            placeholder="e.g. uploads/receipts/2025/01/receipt.jpg"
            autocomplete="off"
            hint="Filesystem path or S3 key of the receipt image. Optional."
        />

        <x-text-field
            name="expense_date"
            type="date"
            label="Expense date"
            :value=$expense?->expense_date
            required
            autocomplete="off"
        />
    </div>
</section>