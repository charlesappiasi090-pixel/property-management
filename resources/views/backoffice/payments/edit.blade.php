@extends('layouts.app')

@section('title', 'Edit payment for '.$payment->formattedAmount)

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.payments.show', $payment)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to payment details
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Edit payment</h2>
            <p class="mt-1 text-sm text-slate-600">
                Changes are recorded in the activity log, with your name against them.
            </p>
        </div>

        <form method="POST" action="{{ route('app.payments.update', $payment) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-flash-messages />

            @include('backoffice.payments._form', ['leases' => $business->leases()->with('tenant')->get()])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.payments.show', $payment)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Saving...">
                    Save changes
                </x-button>
            </div>
        </form>
    </div>
@endsection