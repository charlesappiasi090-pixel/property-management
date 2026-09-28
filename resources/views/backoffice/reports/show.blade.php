@extends('layouts.app')

@section('title', 'Rent roll snapshot – '.$roll->tenant_name)

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to {{ $property->name }}
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Rent roll snapshot – {{ $roll->tenant_name }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            Property: {{ $property->name }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Roll details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $roll->tenant_name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unit</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($roll->unit_label)
                            {{ $roll->unit_label }}
                        @else
                            <span class="text-slate-400">Whole property</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lease period</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $roll->lease_start_date->format('m/d/Y') }} –
                        @if ($roll->lease_end_date)
                            {{ $roll->lease_end_date->format('m/d/Y') }}
                        @else
                            <span class="text-slate-400">present</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly rent</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $roll->formattedMonthlyRent }}
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lease status</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($roll->lease_status === 'active')
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

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Snapshot date</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $roll->snapshot_at->format('m/d/Y') }}
                    </dd>
                </dd>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.properties.show', $property) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $property->name }}
                        </a>
                    </dd>
                </dd>
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $roll)
        <x-button :href="route('app.reports.edit', [$property, $roll])" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $roll)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-roll-{{ $roll->id }}"
            data-confirm-action="{{ route('app.reports.destroy', [$property, $roll]) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove roll
        </x-button>
    @endcan
@endsection