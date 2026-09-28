@extends('layouts.app')

@section('title', 'Add a payment')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.payments.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to payments
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add a payment</h2>
            <p class="mt-1 text-sm text-slate-600">
                Record a rent payment received from a tenant.
            </p>
        </div>

        <form method="POST" action="{{ route('app.payments.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-select-field
                name="lease_id"
                label="Lease"
                :options="$leases->pluck('tenant->displayName', 'id')->toArray()
                ?->prepend('Select a lease')"
                required
                hint="The lease this payment is against. A lease must be active."
            />

            <x-text-field
                name="amount"
                type="text"
                label="Amount"
                required
                placeholder="e.g. 1250 or 1,250.00"
                autocomplete="off"
                hint="Asking rent. Format: number or number with thousand separator and up to two decimal places."
            />

            <x-select-field
                name="status"
                label="Status"
                :options="['active' => 'Active', 'failed' => 'Failed', 'refunded' => 'Refunded']"
                required
            />

            <x-text-field
                name="method"
                type="text"
                label="Payment method"
                placeholder="e.g. bank_transfer, cash, credit_card"
                autocomplete="off"
                hint="Optional. The method used to receive the payment."
            />

            <x-text-field
                name="reference"
                type="text"
                label="Reference"
                placeholder="e.g. Check #1234 or Txn ID abc123"
                autocomplete="off"
                hint="Optional. Check number, transaction ID, or receipt number."
            />

            <x-text-field
                name="payment_date"
                type="date"
                label="Payment date"
                required
                autocomplete="off"
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.payments.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Recording...">
                    <x-icon name="plus" class="size-4" />
                    Record payment
                </x-button>
            </div>
        </form>
    </div>
@endsection