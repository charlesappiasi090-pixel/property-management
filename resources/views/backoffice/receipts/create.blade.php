@extends('layouts.app')

@section('title', 'Add a receipt for payment ' . $payment->formattedAmount)

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.payments.show', $payment)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to payment details
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Add a receipt for {{ $payment->formattedAmount }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Issue a receipt for the payment recorded above.
            </p>
        </div>

        <form method="POST" action="{{ route('app.receipts.store', $payment) }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-text-field
                name="receipt_number"
                type="text"
                label="Receipt number"
                required
                placeholder="e.g. R-001"
                autocomplete="off"
            />

            <x-text-field
                name="amount"
                type="text"
                label="Amount"
                required
                placeholder="e.g. 1250 or 1,250.00"
                autocomplete="off"
                hint="Must match the payment amount."
            />

            <x-text-field
                name="issued_at"
                type="date"
                label="Issued date"
                required
                autocomplete="off"
            />

            <x-text-field
                name="issued_by"
                type="text"
                label="Issued by"
                placeholder="e.g. Jane Smith"
                autocomplete="off"
                hint="Optional. The person who issued the receipt."
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.payments.show', $payment)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Issuing...">
                    <x-icon name="printer" class="size-4" />
                    Issue receipt
                </x-button>
            </div>
        </form>
    </div>
@endsection