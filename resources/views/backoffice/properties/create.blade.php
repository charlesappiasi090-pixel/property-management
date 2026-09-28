@extends('layouts.app')

@section('title', 'Add a property')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.properties.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to properties
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add a property</h2>
            <p class="mt-1 text-sm text-slate-600">
                A building or parcel in {{ $business->name }}'s portfolio. You can add its units straight afterwards.
            </p>
        </div>

        <form method="POST" action="{{ route('app.properties.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            @include('backoffice.properties._form', ['property' => null])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.properties.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Adding...">
                    <x-icon name="plus" class="size-4" />
                    Add property
                </x-button>
            </div>
        </form>
    </div>
@endsection
