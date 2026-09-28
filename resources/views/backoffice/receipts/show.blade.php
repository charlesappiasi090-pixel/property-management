@extends('layouts.app')

@section('title', 'Receipt ' . $receipt->receipt_number . ' – ' . $payment->formattedAmount)

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.payments.show', $payment)" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to payment details
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Receipt {{ $receipt->receipt_number }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            Payment – {{ $payment->formattedAmount }} – {{ $payment->lease->tenant->displayName() }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Receipt details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt number</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $receipt->receipt_number }}</dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $receipt->formattedAmount }}
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Issued date</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $receipt->issued_at->format('m/d/Y') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Issued by</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $receipt->issued_by ?? 'Not recorded' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.tenants.show', $receipt->payment->lease->tenant) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $receipt->payment->lease->tenant->displayName() }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.properties.show', $receipt->payment->lease->property) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $receipt->payment->lease->property->name }}
                        </a>
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $receipt)
        <x-button :href="route('app.receipts.edit', [$payment, $receipt])" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $receipt)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-receipt-{{ $receipt->id }}"
            data-confirm-action="{{ route('app.receipts.destroy', [$payment, $receipt]) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove receipt
        </x-button>
    @endcan
@endsection