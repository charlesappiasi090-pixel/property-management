@extends('layouts.app')

@section('title', 'Create journal entry')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.dashboard')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to dashboard
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Create journal entry
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Record a debit or credit entry for the business.
            </p>
        </div>

        <form method="POST" action="{{ route('app.journals.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-text-field
                name="description"
                type="text"
                label="Description"
                placeholder="e.g. Rent received for Unit 3, Accrued expense Q3"
                required
                autocomplete="off"
            />

            <x-text-field
                name="amount"
                type="text"
                label="Amount"
                placeholder="e.g. 1250.00"
                autocomplete="off"
                required
            />

            <x-select-field
                name="transaction_type"
                label="Transaction type"
                :options="['debit' => 'Debit', 'credit' => 'Credit']"
                required
            />

            <x-button :href="route('app.dashboard')" variant="secondary">Cancel</x-button>

            <x-button type="submit" loading-text="Recording...">
                <x-icon name="plus" class="size-4" />
                Record entry
            </x-button>
        </form>
    </div>
@endsection