@extends('layouts.app')

@section('title', 'Edit receipt ' . $receipt->receipt_number . ' for payment ' . $payment->formattedAmount)

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.receipts.show', [$payment, $receipt])" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to receipt details
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Edit receipt {{ $receipt->receipt_number }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Changes are recorded in the activity log, with your name against them.
            </p>
        </div>

        <form method="POST" action="{{ route('app.receipts.update', [$payment, $receipt]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-flash-messages />

            @include('backoffice.receipts._form', ['payment' => $payment])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.receipts.show', [$payment, $receipt])" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Saving...">
                    Save changes
                </x-button>
            </div>
        </form>
    </div>
@endsection