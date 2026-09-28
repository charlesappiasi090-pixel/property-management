@extends('layouts.app')

@section('title', 'Leases')

@section('headerActions')
    @can('create', \App\Models\Lease::class)
        <x-button :href="route('app.leases.create', $property)" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a lease
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atLeaseLimit = false; // leases have no plan limit in Phase 3
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Leases</h2>
            <p class="mt-1 text-sm text-slate-600">
                Active rental agreements for {{ $property->name }}.
            </p>
        </div>

        @if ($atLeaseLimit)
            <div class="ph-alert ph-alert-warning mb-6" role="status">
                <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold">Your portfolio has no room for another lease.</p>
                    <p class="mt-1 text-sm">
                        Remove a lease you no longer manage, or
                        <a href="{{ route('app.subscription.plans') }}" class="font-semibold underline">upgrade the plan</a>.
                    </p>
                </div>
            </div>
        @endif
    </div>

    <section class="ph-card">
        {{-- ------------------------------------------------------------------ --}}
        {{-- Search and filters                                                 --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="border-b border-slate-200 p-4">
            <form method="GET" action="{{ route('app.leases.index', $property) }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[14rem] flex-1">
                    <x-text-field
                        name="search"
                        label="Search"
                        :value="$search"
                        placeholder="Tenant name or email"
                        autocomplete="off"
                    />
                </div>

                <div class="min-w-[12rem]">
                    <x-select-field
                        name="sort"
                        label="Sort by"
                        :selected="request()->string('sort')->toString() ?: 'start_date'"
                        :options="[
                            'start_date' => 'Start date',
                            'tenant' => 'Tenant',
                            'status' => 'Status',
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
                    <x-button :href="route('app.leases.index', $property)" variant="ghost">Clear</x-button>
                @endif
            </form>
        </div>

        @if ($leases->isEmpty())
            <x-empty-state
                icon="document-text"
                title="{{ $search ? 'No leases match' : 'No leases yet' }}"
                message="{{ $search
                    ? 'Try a different search, or clear the filters to see the whole list.'
                    : 'Add the first lease for this property. It will appear here once a tenant is assigned and a start date is set.' }}"
            >
                @can('create', \App\Models\Lease::class)
                    <x-button :href="route('app.leases.create', $property)" size="sm">Add a lease</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">Leases for {{ $property->name }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">Tenant</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Period</th>
                            <th scope="col">Monthly rent</th>
                            <th scope="col" class="text-right">
                                <span class="ph-sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($leases as $lease)
                            <tr class="{{ $lease->status === 'active' ? '' : 'bg-slate-50/60' }}>
                                <td>
                                    <a
                                        href="{{ route('app.tenants.show', $lease->tenant) }}"
                                        class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                    >
                                        {{ $lease->tenant->displayName() }}
                                    </a>
                                </td>

                                <td>
                                    @if ($lease->unit)
                                        {{ $lease->unit->label }}
                                    @else
                                        <span class="text-slate-400">Whole property</span>
                                    @endif
                                </td>

                                <td>
                                    {{ $lease->start_date->format('m/d/Y') }} –
                                    @if ($lease->end_date)
                                        {{ $lease->end_date->format('m/d/Y') }}
                                    @else
                                        <span class="text-slate-400">present</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap tabular-nums text-slate-600">
                                    {{ $lease->formattedMonthlyRent }}
                                    <span class="text-xs text-slate-400">/ month</span>
                                </td>

                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $lease)
                                            <x-button
                                                :href="route('app.leases.edit', [$lease->property, $lease])"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Edit
                                            </x-button>
                                        @endcan

                                        @can('delete', $lease)
                                            <x-button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-rose-600 hover:bg-rose-50"
                                                data-confirm="remove-lease-{{ $lease->id }}"
                                                data-confirm-action="{{ route('app.leases.destroy', [$lease->property, $lease]) }}"
                                            >
                                                <span class="ph-sr-only">Remove lease {{ $lease->id }}</span>
                                                <x-icon name="trash" class="size-4" />
                                            </x-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($leases->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $leases->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection