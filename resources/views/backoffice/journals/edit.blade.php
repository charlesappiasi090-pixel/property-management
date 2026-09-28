@extends('layouts.app')

@section('title', 'Edit journal entry – {{ $entry->description }}')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.journals.show', $entry)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to entry details
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Edit journal entry
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Changes are recorded in the activity log, with your name against them.
            </p>
        </div>

        <form method="POST" action="{{ route('app.journals.update', $entry) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-flash-messages />

            <x-text-field
                name="description"
                type="text"
                label="Description"
                :value=$entry->description
                required
                autocomplete="off"
            />

            <x-text-field
                name="amount"
                type="text"
                label="Amount"
                :value=number_format($entry->amount, 2)
                autocomplete="off"
                required
            />

            <x-select-field
                name="transaction_type"
                label="Transaction type"
                :options="[$entry->transaction_type => $entry->transaction_type, $entry->transaction_type === 'debit' ? 'credit' : 'debit' => $entry->transaction_type === 'credit' ? 'debit' : 'credit']"
                :selected=$entry->transaction_type
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.journals.show', $entry)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Saving...">
                    Save changes
                </x-button>
            </div>
        </form>
    </div>
@endsection