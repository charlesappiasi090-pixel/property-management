@extends('layouts.app')

@section('title', 'Rent Rolls')

@section('headerActions')
    @can('create', \App\Models\RentRoll::class)
        <x-button :href="route('app.reports.create', $property)" size="sm">
            <x-icon name="plus" class="size-4" />
            Create rent roll
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atRollLimit = false; // rent rolls have no plan limit in Phase 7
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Rent Rolls</h2>
        <p class="mt-1 text-sm text-slate-600">
            Snapshots of active leases for {{ $property->name }}.
        </p>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h3 class="font-semibold">Total rolls: {{ $rolls->total() }}</h3>
            <p class="mt-1 text-sm text-slate-600">
                Active: {{ $rolls->where('lease_status', 'active')->count() }}, 
                Terminated: {{ $rolls->where('lease_status', 'terminated')->count() }}.
            </p>
            <p class="mt-1 text-sm text-slate-600">
                Total monthly rent (active): {{ number_format($metrics['total_monthly_rent']) }}.
            </p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Search and filters                                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="border-b border-slate-200 p-4">
        <form method="GET" action="{{ route('app.reports.index', $property) }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <x-text-field
                    name="search"
                    label="Search"
                    :value="$search"
                    placeholder="Tenant name or property"
                    autocomplete="off"
                />
            </div>

            <div class="min-w-[12rem]">
                <x-select-field
                    name="sort"
                    label="Sort by"
                    :selected="request()->string('sort')->toString() ?: 'snapshot_at'"
                    :options="[
                        'snapshot_at' => 'Snapshot date',
                        'tenant_name' => 'Tenant',
                        'lease_status' => 'Status',
                        'monthly_rent' => 'Monthly rent',
                    ]"
                />
            </div>

            <div class="min-w-[10rem]">
                <x-select-field
                    name="direction"
                    label="Order"
                    :selected="request()->string('direction')->toString() ?: 'desc'"
                    :options="['desc' => 'Newest first', 'asc' => 'Oldest first']"
                />
            </div>

            <x-button type="submit" variant="secondary">Apply</x-button>

            @if ($search)
                <x-button :href="route('app.reports.index', $property)" variant="ghost">Clear</x-button>
            @endif
        </form>
    </div>

    @if ($rolls->isEmpty())
        <x-empty-state
            icon="document-text"
            title="{{ $search ? 'No rolls match' : 'No rent rolls yet' }}"
            message="{{ $search
                ? 'Try a different search, or clear the filters to see the whole portfolio.'
                : 'Create the first rent roll snapshot to begin tracking your portfolio.' }}"
        >
            @can('create', \App\Models\RentRoll::class)
                <x-button :href="route('app.reports.create', $property)" size="sm">Create rent roll</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="ph-scroll overflow-x-auto">
            <table class="ph-table">
                <caption class="ph-sr-only">Rent rolls for {{ $property->name }}</caption>

                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col">Unit</th>
                        <th scope="col">Period</th>
                        <th scope="col">Monthly rent</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">
                            <span class="ph-sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rolls as $roll)
                        <tr class="{{ $roll->lease_status === 'active' ? '' : 'bg-slate-50/60' }}">
                            <td>
                                <a
                                    href="{{ route('app.tenants.show', $roll->tenant) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                >
                                    {{ $roll->tenant_name }}
                                </a>
                            </td>

                            <td>
                                @if ($roll->unit_label)
                                    {{ $roll->unit_label }}
                                @else
                                    <span class="text-slate-400">Whole property</span>
                                @endif
                            </td>

                            <td>
                                {{ $roll->lease_start_date->format('m/d/Y') }} –
                                @if ($roll->lease_end_date)
                                    {{ $roll->lease_end_date->format('m/d/Y') }}
                                @else
                                    <span class="text-slate-400">present</span>
                                @endif
                            </td>

                            <td class="tabular-nums text-slate-600">
                                {{ $roll->formattedMonthlyRent }}
                                <span class="text-xs text-slate-400">/ month</span>
                            </td>

                            <td>
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
                            </td>

                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $roll)
                                        <x-button
                                            :href="route('app.reports.edit', [$property, $roll])"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Edit
                                        </x-button>
                                    @endcan

                                    @can('delete', $roll)
                                        <x-button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="remove-roll-{{ $roll->id }}"
                                            data-confirm-action="{{ route('app.reports.destroy', [$property, $roll]) }}"
                                        >
                                            <span class="ph-sr-only">Remove roll {{ $roll->id }}</span>
                                            <x-icon name="trash" class="size-4" />
                                        </x-button>
                                    @endcan
                                </td>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($rolls->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $rolls->links() }}
            </div>
        @endif
    @endif
@endsection