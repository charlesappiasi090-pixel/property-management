@extends('layouts.app')

@section('title', 'Create rent roll snapshot')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to {{ $property->name }}
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Create rent roll snapshot
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Capture the state of leases for {{ $property->name }} at this moment.
            </p>
        </div>

        <form method="POST" action="{{ route('app.reports.store', $property) }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-select-field
                name="property_id"
                label="Property"
                :selected=$property->id
                :options=$propertyOptions
                required
            />

            <x-text-field
                name="snapshot_at"
                type="date"
                label="Snapshot date"
                :value=now()
                required
                autocomplete="off"
            />

            <x-text-field
                name="tenant_name"
                type="text"
                label="Tenant name"
                placeholder="e.g. Oakwood Properties"
                placeholder="Optional."
            />

            <x-select-field
                name="lease_id"
                label="Lease"
                :options=$leaseOptions
                placeholder="Optional."
            />

            <x-text-field
                name="unit_label"
                type="text"
                label="Unit label"
                placeholder="Optional."
            />

            <x-text-field
                name="lease_start_date"
                type="date"
                label="Lease start date"
                placeholder="Optional."
            />

            <x-text-field
                name="lease_end_date"
                type="date"
                label="Lease end date"
                placeholder="Optional."
            />

            <x-text-field
                name="monthly_rent"
                type="text"
                label="Monthly rent"
                placeholder="e.g. 1250 or 1,250.00"
                autocomplete="off"
            />

            <x-select-field
                name="lease_status"
                label="Lease status"
                :options="['active' => 'Active', 'terminated' => 'Terminated']"
                required
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.properties.show', $property)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Creating...">
                    <x-icon name="plus" class="size-4" />
                    Create snapshot
                </x-button>
            </div>
        </form>
    </div>
@endsection