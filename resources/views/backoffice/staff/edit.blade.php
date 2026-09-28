@extends('layouts.app')

@section('title', $member->name)

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <x-button :href="route('app.staff.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to team
            </x-button>

            <div class="flex items-center gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-100 text-base font-semibold text-brand-700">
                    {{ $member->initials() }}
                </span>

                <div class="min-w-0">
                    <h2 class="truncate text-xl font-bold tracking-tight text-slate-900">{{ $member->name }}</h2>
                    <p class="truncate text-sm text-slate-600">
                        {{ $member->email }}
                        @if ($member->pivot->job_title)
                            <span class="text-slate-400">&middot; {{ $member->pivot->job_title }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('app.staff.update', $member) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-flash-messages />

            <section class="ph-card">
                <div class="ph-card-body space-y-5">
                    <x-text-field
                        name="job_title"
                        label="Job title"
                        autocomplete="organization-title"
                        :value="$member->pivot->job_title"
                        hint="Optional. Shown next to their name across the app."
                    />
                </div>
            </section>

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
                                    $selected = old('role', $currentRole?->value) === $role->value;
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

                    {{-- `owner` is reachable in UpdateStaffRequest when the actor
                         holds `staff.grant_owner`, but it is not in
                         `isAssignableByOwner()`. The dedicated promotion flow
                         arrives with staff.grant_owner; until then the option is
                         not offered here, so the UI never shows a control the
                         server would reject. --}}
                    <p class="ph-hint">
                        Need to make {{ $member->is(auth()->user()) ? 'yourself' : $member->name }} an owner? That's a
                        deliberate, separately-audited step — ask an existing owner.
                    </p>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <x-button :href="route('app.staff.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Saving...">Save changes</x-button>
            </div>
        </form>
    </div>
@endsection
