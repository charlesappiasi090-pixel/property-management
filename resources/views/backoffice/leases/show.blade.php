@extends('layouts.app')

@section('title', $lease->tenant->displayName() . ' – ' . $lease->property->name)

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.properties.show', $lease->property)" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to {{ $lease->property->name }}
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Lease – {{ $lease->tenant->displayName() }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ $lease->property->name }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Lease details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $lease->tenant->displayName() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.properties.show', $lease->property) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $lease->property->name }}
                        </a>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unit</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($lease->unit)
                            {{ $lease->unit->label }}
                        @else
                            <span class="text-slate-400">Whole property</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Start date</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $lease->start_date->format('d/m/Y') }}
                    </dd>
                </div>

                @if ($lease->end_date)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">End date</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $lease->end_date->format('d/m/Y') }}
                        </dd>
                    </div>
                @endif

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly rent</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $lease->formattedMonthlyRent }}
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                @if ($lease->security_deposit)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Security deposit</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $lease->security_deposit }}
                            <span class="text-xs text-slate-400"> (amount)</span>
                        </dd>
                    </div>
                @endif

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($lease->status === 'active')
                            <x-status-badge
                                label="Active"
                                class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                            />
                        @else
                            <x-status-badge
                                label="Terminated"
                                class="bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                            />
                        @endif
                    </dd>
                </dd>
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $lease)
        <x-button :href="route('app.leases.edit', [$lease->property, $lease])" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $lease)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-lease-{{ $lease->id }}"
            data-confirm-action="{{ route('app.leases.destroy', [$lease->property, $lease]) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove lease
        </x-button>
    @endcan
@endsection