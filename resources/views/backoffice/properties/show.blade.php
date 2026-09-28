@extends('layouts.app')

@section('title', $property->name)

@section('headerActions')
    @can('update', $property)
        <x-button :href="route('app.properties.edit', $property)" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan
@endsection

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.properties.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to properties
        </x-button>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="flex flex-wrap items-center gap-3 text-xl font-bold tracking-tight text-slate-900">
                    {{ $property->name }}

                    @if (! $property->is_active)
                        <x-status-badge
                            label="Archived"
                            class="bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                        />
                    @endif
                </h2>

                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                    <x-icon name="map-pin" class="size-4 shrink-0" />
                    @if ($property->addressLine())
                        {{ $property->addressLine() }}
                    @else
                        <span class="text-slate-400">No address recorded yet</span>
                    @endif
                </p>
            </div>

            <x-status-badge
                :label="$property->property_type->label()"
                class="bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200"
            />
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                            --}}
    {{-- Rendered as a definition list rather than a card of labelled fields: --}}
    {{-- a <dl> lets a screen reader announce "Owner of record: Jane Doe" as --}}
    {{-- a pair, where a table of two columns of divs announces it as two       --}}
    {{-- unrelated strings.                                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Owner of record</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($property->owner_name)
                            {{ $property->owner_name }}
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>

                    @if ($property->owner_email || $property->owner_phone)
                        <dd class="mt-1 text-sm text-slate-600">
                            @if ($property->owner_email)
                                <a href="mailto:{{ $property->owner_email }}" class="hover:text-brand-700 hover:underline">
                                    {{ $property->owner_email }}
                                </a>
                            @endif

                            @if ($property->owner_email && $property->owner_phone)
                                <span class="text-slate-400">&middot;</span>
                            @endif

                            @if ($property->owner_phone)
                                {{ $property->owner_phone }}
                            @endif
                        </dd>
                    @endif
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Year built</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($property->year_built)
                            {{ number_format($property->year_built) }}
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Added</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <time datetime="{{ $property->created_at?->toIso8601String() }}">
                            {{ $property->created_at?->diffForHumans() ?? 'Unknown' }}
                        </time>
                    </dd>
                </div>

                @if ($property->description)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                        {{-- whitespace-pre-line so a landlord's line breaks in a
                             description survive. `nl2br(e())` would also work
                             but echoes the escaped string twice in the DOM. --}}
                        <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $property->description }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Units                                                              --}}
    {{--                                                                     --}}
    {{-- The panel is omitted entirely for commercial and land, which have no --}}
    {{-- residential inventory by definition (`allowsResidentialUnits()`).   --}}
    {{-- Showing an always-empty "no units yet, add one" panel there would be  --}}
    {{-- an invitation to create data the data model does not allow.          --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($property->showsUnitPanel())
        <section class="ph-card mt-6">
            <div class="ph-card-header">
                <h3 class="ph-card-title">
                    Units
                    <span class="ml-1 font-normal text-slate-500">
                        {{ number_format($activeUnitCount) }} active
                        @if ($unitQuota['limit'] !== null)
                            <span class="text-slate-400">
                                of {{ number_format($unitQuota['used']) }}/{{ number_format($unitQuota['limit']) }} in plan
                            </span>
                        @endif
                    </span>
                </h3>

                @if ($canAddUnit)
                    @php
                        /*
                         * The button is offered whenever the user may use it and
                         * the limit message appears beside it when the plan is
                         * full. Hiding the button at the limit would leave a user
                         * who clicks "Add a property" on a full plan with no way
                         * to discover why nothing happened.
                         */
                        $unitLimitReached = $unitQuota['limit'] !== null && $unitQuota['used'] >= $unitQuota['limit'];
                    @endphp

                    <x-button :href="route('app.units.create', $property)" size="sm">
                        <x-icon name="plus" class="size-4" />
                        Add a unit
                    </x-button>
                @endif
            </div>

            @if ($unitQuota['limit'] !== null && $unitQuota['used'] >= $unitQuota['limit'])
                <div class="border-b border-slate-200 px-5 py-3">
                    <p class="flex items-start gap-2 text-sm text-amber-800">
                        <x-icon name="exclamation-triangle" class="size-4 shrink-0" />
                        <span>
                            Your plan allows {{ number_format($unitQuota['limit']) }}
                            {{ \Illuminate\Support\Str::plural('unit', $unitQuota['limit']) }},
                            and {{ number_format($unitQuota['used']) }} are on your account.
                            <a href="{{ route('app.subscription.plans') }}" class="font-semibold underline">Upgrade</a>
                            to add more.
                        </span>
                    </p>
                </div>
            @endif

            @if ($units->isEmpty())
                <x-empty-state
                    icon="home"
                    title="No units yet"
                    message="Units are the individual homes or spaces inside this property. Add them here, then create leases against them."
                >
                    @if ($canAddUnit)
                        <x-button :href="route('app.units.create', $property)" size="sm">Add the first unit</x-button>
                    @endif
                </x-empty-state>
            @else
                <div class="ph-scroll overflow-x-auto">
                    <table class="ph-table">
                        <caption class="ph-sr-only">Units in {{ $property->name }}</caption>

                        <thead>
                            <tr>
                                <th scope="col">Unit</th>
                                <th scope="col">Specification</th>
                                <th scope="col">Asking rent</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">
                                    <span class="ph-sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($units as $unit)
                                <tr class="{{ $unit->is_active ? '' : 'bg-slate-50/60' }}">
                                    <td>
                                        <p class="font-medium text-slate-900">{{ $unit->label }}</p>

                                        @if ($unit->floor)
                                            <p class="mt-0.5 text-xs text-slate-500">Floor {{ $unit->floor }}</p>
                                        @endif
                                    </td>

                                    <td class="text-slate-600">
                                        {{ $unit->specification() }}
                                    </td>

                                    <td class="whitespace-nowrap tabular-nums text-slate-600">
                                        @if ($unit->askingRent())
                                            {{-- Currency comes from the BUSINESS, not from
                                                 the global config: two landlords can
                                                 bill in different currencies, and a
                                                 rent figure is meaningless without
                                                 the right symbol. --}}
                                            {{ $unit->askingRent()->format($business->currency, $business->currencySymbol()) }}
                                            <span class="text-xs text-slate-400">/ month</span>
                                        @else
                                            <span class="text-slate-400">Not set</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($unit->is_active)
                                            <x-status-badge
                                                label="In portfolio"
                                                class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                            />
                                        @else
                                            <x-status-badge
                                                label="Not letting"
                                                class="bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                                            />
                                        @endif
                                    </td>

                                    <td class="text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @can('update', $unit)
                                                <x-button
                                                    :href="route('app.units.edit', [$property, $unit])"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    Edit
                                                </x-button>
                                            @endcan

                                            @can('delete', $unit)
                                                <x-button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-rose-600 hover:bg-rose-50"
                                                    data-confirm="remove-unit-{{ $unit->id }}"
                                                    data-confirm-action="{{ route('app.units.destroy', [$property, $unit]) }}"
                                                >
                                                    <span class="ph-sr-only">Remove unit {{ $unit->label }}</span>
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
            @endif
        </section>

        {{--
            Confirmation dialogs live OUTSIDE the <table>, for the same reason
            the staff index puts them after it: a <dialog> is a top-level
            element and cannot legally sit inside <tbody>. Browsers "fix" the
            invalid nesting by hoisting the element, which detached it from the
            row it belonged to.
        --}}
        @can('delete', \App\Models\Unit::class)
            @foreach ($units as $unit)
                <x-confirm-dialog
                    id="remove-unit-{{ $unit->id }}"
                    title="Remove unit {{ $unit->label }}?"
                    message="{{ $property->name }} keeps its other units. This unit's history stays in the activity log."
                    confirm-text="Remove unit"
                    :action="route('app.units.destroy', [$property, $unit])"
                />
            @endforeach
        @endcan
    @else
        <section class="ph-card mt-6">
            <x-empty-state
                icon="building"
                title="No units for this property type"
                message="{{ $property->property_type->label() }} properties are managed as a single asset, so they have no individual units. Change the type if that is wrong."
            />
        </section>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Danger zone                                                        --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('delete', $property)
        <section class="ph-card mt-6">
            <div class="ph-card-header">
                <h3 class="ph-card-title">Remove this property</h3>
            </div>

            <div class="ph-card-body">
                <p class="text-sm text-slate-600">
                    Removing {{ $property->name }} archives it and
                    @if ($units->isNotEmpty())
                        all {{ number_format($units->count()) }} of its units
                    @else
                        any units it has
                    @endif.
                    Nothing is erased, and the record stays in your activity log.
                </p>

                <x-button
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="mt-4 text-rose-600 hover:bg-rose-50"
                    data-confirm="remove-property-{{ $property->id }}"
                    data-confirm-action="{{ route('app.properties.destroy', $property) }}"
                >
                    <x-icon name="trash" class="size-4" />
                    Remove {{ $property->name }}
                </x-button>
            </div>
        </section>

        <x-confirm-dialog
            id="remove-property-{{ $property->id }}"
            title="Remove {{ $property->name }}?"
            message="The property and its units are archived. They leave the working portfolio but keep their history."
            confirm-text="Remove property"
            :action="route('app.properties.destroy', $property)"
        />
    @endcan
@endsection
