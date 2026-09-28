@extends('layouts.app')

@section('title', 'Properties')

@section('headerActions')
    @can('create', \App\Models\Property::class)
        <x-button :href="route('app.properties.create')" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a property
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        /*
         * The "Add a property" button is shown or hidden by the policy, but the
         * PLAN may also have no room left. Those are different problems: a
         * missing permission is not fixable by buying a plan, and a full plan is
         * not fixable by asking an owner for permission. So the button is
         * offered whenever the user may use it, and the quota is surfaced as its
         * own message with the upgrade path beside it. Hiding the button at the
         * limit would leave the user with no way to find out why.
         */
        $atPropertyLimit = $quota['limit'] !== null && $quota['used'] >= $quota['limit'];
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Properties</h2>
            <p class="mt-1 text-sm text-slate-600">
                @if ($quota['limit'] === null)
                    Every building and parcel {{ $business->name }} manages.
                @else
                    {{ number_format($quota['used']) }} of {{ number_format($quota['limit']) }}
                    {{ \Illuminate\Support\Str::plural('property', $quota['limit']) }} used.
                @endif
            </p>
        </div>
    </div>

    @if ($atPropertyLimit)
        {{--
            No <x-flash-messages /> here: the app shell already renders one for
            the whole page, and a second copy would announce the same success
            message twice to a screen reader.

            This block is a persistent condition rather than a flash, which is
            why it is inline markup and not a session message: it is true on
            every visit until the plan changes or a property is removed.
        --}}
        <div class="ph-alert ph-alert-warning mb-6" role="status">
            <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
            <div>
                <p class="text-sm font-semibold">Your plan has no room for another property.</p>
                <p class="mt-1 text-sm">
                    Remove a property you no longer manage, or
                    <a href="{{ route('app.subscription.plans') }}" class="font-semibold underline">upgrade the plan</a>.
                </p>
            </div>
        </div>
    @endif

    <section class="ph-card">
        {{-- ------------------------------------------------------------------ --}}
        {{-- Search and filters                                                --}}
        {{-- The form is a GET so the search survives a reload and can be      --}}
        {{-- bookmarked. A POST would keep the state in the session, where a   --}}
        {{-- shared link cannot reproduce what the user was looking at.        --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="border-b border-slate-200 p-4">
            <form method="GET" action="{{ route('app.properties.index') }}" class="flex flex-wrap items-end gap-3">
                @if ($showArchived)
                    <input type="hidden" name="show_archived" value="1">
                @endif

                <div class="min-w-[14rem] flex-1">
                    <x-text-field
                        name="search"
                        label="Search"
                        :value="$search"
                        placeholder="Name, street, city or owner"
                        autocomplete="off"
                    />
                </div>

                <div class="min-w-[12rem]">
                    <x-select-field
                        name="sort"
                        label="Sort by"
                        :selected="request()->string('sort')->toString() ?: 'name'"
                        :options="[
                            'name' => 'Name',
                            'city' => 'City',
                            'type' => 'Type',
                            'units' => 'Number of units',
                            'created_at' => 'Date added',
                        ]"
                    />
                </div>

                <div class="min-w-[10rem]">
                    <x-select-field
                        name="direction"
                        label="Order"
                        :selected="request()->string('direction')->toString() ?: 'asc'"
                        :options="['asc' => 'A to Z', 'desc' => 'Z to A']"
                    />
                </div>

                <x-button type="submit" variant="secondary">Apply</x-button>

                {{--
                    Archived properties are a LINK rather than a checkbox.

                    A checkbox in a GET form that is not submitted when unticked
                    means "off" and "off, but I want to clear the search" are
                    the same request, so clearing one silently re-shows archived
                    rows. Two links with explicit query strings have no such
                    ambiguity, and the current state is always visible as a
                    link to the other state.
                --}}
                @if ($showArchived || $search)
                    <x-button :href="route('app.properties.index')" variant="ghost">Clear</x-button>
                @endif

                @if ($showArchived)
                    <a
                        href="{{ route('app.properties.index', array_filter(['search' => $search])) }}"
                        class="ph-btn ph-btn-ghost ph-btn-sm"
                    >
                        Hide archived
                    </a>
                @else
                    <a
                        href="{{ route('app.properties.index', array_filter(['search' => $search, 'show_archived' => 1])) }}"
                        class="ph-btn ph-btn-ghost ph-btn-sm"
                    >
                        Show archived
                    </a>
                @endif
            </form>
        </div>

        @if ($properties->isEmpty())
            <x-empty-state
                icon="building"
                title="{{ $search || $showArchived ? 'No properties match' : 'No properties yet' }}"
                message="{{ $search || $showArchived
                    ? 'Try a different search, or clear the filters to see the whole portfolio.'
                    : 'Add the first building or parcel you manage. Its units, leases and documents all hang off it.' }}"
            >
                @can('create', \App\Models\Property::class)
                    @unless ($search || $showArchived)
                        <x-button :href="route('app.properties.create')" size="sm">Add a property</x-button>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">Properties managed by {{ $business->name }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">Property</th>
                            <th scope="col">Type</th>
                            <th scope="col">Units</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">
                                <span class="ph-sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($properties as $property)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('app.properties.show', $property) }}"
                                        class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                    >
                                        {{ $property->name }}
                                    </a>

                                    @if ($property->addressLine())
                                        <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500">
                                            <x-icon name="map-pin" class="size-3.5 shrink-0" />
                                            <span class="truncate">{{ $property->addressLine() }}</span>
                                        </p>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap text-slate-600">
                                    {{ $property->property_type->label() }}
                                </td>

                                <td class="whitespace-nowrap tabular-nums text-slate-600">
                                    @if ($property->property_type->allowsResidentialUnits())
                                        {{ number_format($property->units_count) }}
                                    @else
                                        <span class="text-slate-400" title="This property type has no units">&mdash;</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($property->is_active)
                                        <x-status-badge
                                            label="Active"
                                            class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                        />
                                    @else
                                        <x-status-badge
                                            label="Archived"
                                            class="bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                                        />
                                    @endif
                                </td>

                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button
                                            :href="route('app.properties.show', $property)"
                                            variant="secondary"
                                            size="sm"
                                        >
                                            View
                                        </x-button>

                                        {{--
                                            Gated on the permission the route
                                            enforces, not on `properties.view`:
                                            an accountant sees the portfolio but
                                            cannot change it, and offering the
                                            link would only earn a 403.
                                        --}}
                                        @can('update', $property)
                                            <x-button
                                                :href="route('app.properties.edit', $property)"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Edit
                                            </x-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($properties->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $properties->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
