@extends('layouts.app')

@section('title', $payment->formattedAmount . ' – ' . $payment->lease->tenant->displayName())

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.payments.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to payments
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Payment – {{ $payment->formattedAmount }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ $payment->lease->tenant->displayName() }} – {{ $payment->lease->property->name }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Payment details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $payment->lease->tenant->displayName() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.properties.show', $payment->lease->property) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $payment->lease->property->name }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lease start</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $payment->lease->start_date->format('m/d/Y') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $payment->formattedAmount }}
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <x-status-badge
                            label=$payment->statusLabel()
                            class="bg-{$payment->status === 'active' ? 'emerald' : ($payment->status === 'failed' ? 'rose' : 'slate')}-
                                50 text-{$payment->status === 'active' ? 'emerald-700' : ($payment->status === 'failed' ? 'rose-600' : 'slate-600')} ring-1 ring-inset ring-{$payment->status === 'active' ? 'emerald-200' : ($payment->status === 'failed' ? 'rose-200' : 'slate-200')}
                        />
                    </dd>
                </dd>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment date</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $payment->payment_date->format('m/d/Y') }}
                    </dd>
                </div>

                @if ($payment->reference)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $payment->reference }}</dd>
                    </div>
                @endif

                @if ($payment->method)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Method</dt>
                        <dd class="mt-1 text-sm text-slate-900">{{ $payment->method }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $payment)
        <x-button :href="route('app.payments.edit', $payment)" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $payment)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-payment-{{ $payment->id }}"
            data-confirm-action="{{ route('app.payments.destroy', $payment) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove payment
        </x-button>
    @endcan
@endsection