@extends('layouts.app')

@section('title', 'Add a maintenance request')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to {{ $property->name }}
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add a maintenance request</h2>
            <p class="mt-1 text-sm text-slate-600">
                Record a new maintenance request for {{ $property->name }}.
            </p>
        </div>

        <form method="POST" action="{{ route('app.maintenance.store', $property) }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            @include('backoffice.maintenance._form', [
                'propertyOptions' => \App\Models\Property::active()->pluck('name', 'id')->toArray(),
                'priorityOptions' => [ 'routine' => 'Routine', 'urgent' => 'Urgent', 'emergency' => 'Emergency' ],
                'categoryOptions' => [ 'plumbing' => 'Plumbing', 'electrical' => 'Electrical', 'hvac' => 'HVAC', 'cosmetic' => 'Cosmetic', 'structural' => 'Structural', 'other' => 'Other' ],
            ])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.properties.show', $property)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Creating...">
                    <x-icon name="plus" class="size-4" />
                    Create request
                </x-button>
            </div>
        </form>
    </div>
@endsection