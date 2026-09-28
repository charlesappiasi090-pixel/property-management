@extends('layouts.app')

@section('title', 'Add an expense')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to {{ $property->name }}
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add an expense</h2>
            <p class="mt-1 text-sm text-slate-600">
                Record a cost incurred by {{ $property->name }}.
            </p>
        </div>

        <form method="POST" action="{{ route('app.expenses.store', $property) }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-select-field
                name="property_id"
                label="Property"
                :selected=$property->id
                :options=$propertyOptions
                required
                hint="The property this expense relates to."
            />

            <x-select-field
                name="category"
                label="Category"
                :options=$categories
                required
                hint="The type of expense."
            />

            <x-text-field
                name="amount"
                type="text"
                label="Amount"
                required
                placeholder="e.g. 1250 or 1,250.00"
                autocomplete="off"
                hint="Asking amount. Format: number or number with thousand separator and up to two decimal places."
            />

            <x-select-field
                name="status"
                label="Status"
                :options="['pending' => 'Pending', 'approved' => 'Approved', 'reimbursed' => 'Reimbursed', 'written_off' => 'Written off']"
                required
            />

            <x-text-field
                name="description"
                label="Description"
                rows="3"
                autocomplete="off"
                hint="Anything the team needs to know: vendor, purpose, notes."
            />

            <x-text-field
                name="receipt_path"
                type="text"
                label="Receipt path"
                placeholder="e.g. uploads/receipts/2025/01/receipt.jpg"
                autocomplete="off"
                hint="Filesystem path or S3 key of the receipt image. Optional."
            />

            <x-text-field
                name="expense_date"
                type="date"
                label="Expense date"
                required
                autocomplete="off"
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.properties.show', $property)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Recording...">
                    <x-icon name="plus" class="size-4" />
                    Record expense
                </x-button>
            </div>
        </form>
    </div>
@endsection