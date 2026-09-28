@extends('layouts.app')

@section('title', 'Edit expense for '.$expense->categoryLabel())

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to {{ $property->name }}
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                Edit expense
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Changes are recorded in the activity log, with your name against them.
            </p>
        </div>

        <form method="POST" action="{{ route('app.expenses.update', [$property, $expense]) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-flash-messages />

            @include('backoffice.expenses._form', [
                'property' => $property,
                'propertyOptions' => \App\Models\Property::active()->pluck('name', 'id')->toArray(),
                'categories' => ExpenseCategory::cases(),
                'expense' => $expense,
            ])

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.properties.show', $property)" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Saving...">
                    Save changes
                </x-button>
            </div>
        </form>
    </div>
@endsection