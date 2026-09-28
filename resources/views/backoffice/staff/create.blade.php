@extends('layouts.app')

@section('title', 'Add a team member')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <x-button :href="route('app.staff.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to team
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add a team member</h2>
            <p class="mt-1 text-sm text-slate-600">
                They'll get access to {{ $business->name }} with the role you choose.
            </p>
        </div>

        <form method="POST" action="{{ route('app.staff.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <section class="ph-card">
                <div class="ph-card-body space-y-5">
                    <x-text-field
                        name="email"
                        type="email"
                        label="Email address"
                        required
                        autocomplete="email"
                        placeholder="colleague@example.com"
                        hint="They need an existing PropertyHub account. Invitations by email arrive in a later phase."
                    />

                    <x-text-field
                        name="job_title"
                        label="Job title"
                        autocomplete="organization-title"
                        placeholder="e.g. Portfolio Manager"
                        hint="Optional. Left blank, we use the role name."
                    />
                </div>
            </section>

            {{-- ---------------------------------------------------------------- --}}
            {{-- Role selection                                                   --}}
            {{-- Radio cards, not a <select>: each option carries a paragraph
                 explaining its blast radius, which is the whole point of
                 choosing carefully. A dropdown cannot show that.          --}}
            {{-- ---------------------------------------------------------------- --}}
            <section class="ph-card">
                <div class="ph-card-header">
                    <h3 class="ph-card-title">Access level</h3>
                </div>

                <div class="ph-card-body">
                    <fieldset>
                        <legend class="ph-sr-only">Role</legend>

                        <div class="space-y-3">
                            @foreach ($roles as $role)
                                @php
                                    $inputId = 'role-'.$role->value;
                                    $selected = old('role', \App\Enums\Role::PROPERTY_MANAGER->value) === $role->value;
                                @endphp

                                <label
                                    for="{{ $inputId }}"
                                    class="flex cursor-pointer gap-3 rounded-xl border-2 p-4 transition-colors
                                           hover:border-slate-300 has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60
                                           {{ $selected ? 'border-brand-600 bg-brand-50/60' : 'border-slate-200' }}"
                                >
                                    <input
                                        id="{{ $inputId }}"
                                        type="radio"
                                        name="role"
                                        value="{{ $role->value }}"
                                        @checked($selected)
                                        required
                                        @if ($errors->has('role')) aria-invalid="true" @endif
                                        class="mt-1 border-slate-300 text-brand-600 focus:ring-brand-500"
                                    >

                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $role->label() }}</span>
                                        <span class="mt-0.5 block text-sm text-slate-600">{{ $role->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('role')
                            <p class="ph-field-error" role="alert">
                                <x-icon name="exclamation-triangle" class="size-4 shrink-0" />
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </fieldset>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.staff.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Adding...">
                    Add to team
                </x-button>
            </div>
        </form>
    </div>
@endsection
