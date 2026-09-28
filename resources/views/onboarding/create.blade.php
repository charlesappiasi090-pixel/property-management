@extends('layouts.guest', ['title' => 'Set up your business'])

@php
    $planValue = old('plan', $defaultPlan);
    $trialDays = $plans->max('trial_days') ?? 0;
@endphp

<div class="mx-auto w-full max-w-3xl">
    {{-- ------------------------------------------------------------------ --}}
    {{-- Heading                                                          --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
            Let's set up your business
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            This takes about a minute, {{ $trialDays > 0 ? 'and you get a '.$trialDays.'-day free trial' : 'and you can change anything later' }}.
            No card required.
        </p>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}" class="mt-8 space-y-6">
        @csrf

        <x-flash-messages />

        {{-- Errors that are not tied to a single field (e.g. a failed slug
             uniqueness check on a trimmed value) would otherwise scroll past
             unnoticed. --}}
        @if ($errors->any() && ! $errors->hasAny(['name', 'slug', 'plan']))
            <div class="ph-alert ph-alert-danger" role="alert">
                <x-icon name="x-circle" class="size-5 shrink-0" />
                <p class="text-sm">Please correct the highlighted fields below.</p>
            </div>
        @endif

        {{-- ---------------------------------------------------------------- -- --}}
        {{-- Business identity                                                --}}
        {{-- ---------------------------------------------------------------- -- --}}
        <section class="ph-card">
            <div class="ph-card-header">
                <div>
                    <h2 class="ph-card-title">Your business</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        This is how your team will see it across PropertyHub.
                    </p>
                </div>
            </div>

            <div class="ph-card-body space-y-5">
                <x-text-field
                    name="name"
                    label="Business name"
                    required
                    autocomplete="organization"
                    placeholder="e.g. Northwind Property Group"
                    hint="The trading name. You can add a registered legal name later in settings."
                />

                {{--
                    The slug is derived from the name as the user types, but stays
                    editable: two businesses can share a trading name and still
                    need distinct identifiers. The derivation is a CONVENIENCE,
                    never a constraint — the server still validates and
                    de-duplicates it.

                    No URL prefix is rendered on purpose. Nothing routes on the
                    slug yet, so showing `propertyhub.app/` would promise a
                    public address that does not exist.
                --}}
                <x-text-field
                    name="slug"
                    label="Web address"
                    placeholder="northwind-property-group"
                    hint="Lowercase letters, numbers and single hyphens. Used in links and emails."
                />
            </div>
        </section>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Plan selection                                                    --}}
        {{-- ------------------------------------------------------------------ --}}
        <section class="ph-card">
            <div class="ph-card-header">
                <div>
                    <h2 class="ph-card-title">Choose a plan</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        Every plan includes the full trial. Switch or cancel whenever you like.
                    </p>
                </div>
            </div>

            <div class="ph-card-body">
                <fieldset>
                    <legend class="ph-sr-only">Plan</legend>

                    <div class="space-y-3" data-plan-list>
                        @foreach ($plans as $plan)
                            @php
                                $selected = (string) $planValue === (string) $plan->code;
                                $inputId = 'plan-'.$plan->code;
                            @endphp

                            <label
                                for="{{ $inputId }}"
                                class="group relative flex cursor-pointer flex-col gap-4 rounded-xl border-2 p-5 transition-colors
                                       has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50/60
                                       hover:border-slate-300 has-[:checked]:hover:border-brand-600
                                       {{ $selected ? 'border-brand-600 bg-brand-50/60' : 'border-slate-200' }}"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <input
                                            id="{{ $inputId }}"
                                            type="radio"
                                            name="plan"
                                            value="{{ $plan->code }}"
                                            @checked($selected)
                                            @if ($errors->has('plan')) aria-invalid="true" @endif
                                            required
                                            class="mt-1 border-slate-300 text-brand-600 focus:ring-brand-500"
                                        >

                                        <div>
                                            <span class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-slate-900">{{ $plan->name }}</span>
                                                @if ($plan->code === $defaultPlan)
                                                    <span class="ph-badge bg-brand-100 text-brand-700">Most popular</span>
                                                @endif
                                            </span>

                                            @if ($plan->tagline)
                                                <span class="mt-0.5 block text-sm text-slate-500">{{ $plan->tagline }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <span class="block text-lg font-bold text-slate-900">
                                            {{ $plan->formattedPrice() }}
                                        </span>
                                        <span class="block text-xs text-slate-500">
                                            per {{ $plan->intervalLabel() }}
                                        </span>
                                    </div>
                                </div>

                                @if ($plan->quotaFor('properties') !== null || $plan->quotaFor('staff') !== null)
                                    <dl class="flex flex-wrap gap-x-6 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-600 sm:ml-7">
                                        @foreach (['properties' => 'Properties', 'units' => 'Units', 'staff' => 'Staff seats'] as $key => $label)
                                            @php $quota = $plan->quotaFor($key); @endphp

                                            @if ($quota !== null)
                                                <div class="flex items-center gap-1">
                                                    <dt>{{ $label }}:</dt>
                                                    <dd class="font-semibold text-slate-800">{{ $quota }}</dd>
                                                </div>
                                            @endif
                                        @endforeach
                                    </dl>
                                @endif
                            </label>
                        @endforeach
                    </div>

                    @error('plan')
                        <p class="ph-field-error" role="alert">
                            <x-icon name="exclamation-triangle" class="size-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </fieldset>
            </div>
        </section>

        {{-- ------------------------------------------------------------------ --}}
        {{-- Submit                                                            --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-center text-xs text-slate-500 sm:text-left">
                You can invite your team and add properties as soon as you're inside.
            </p>

            <x-button type="submit" class="w-full sm:w-auto" loading-text="Creating your workspace...">
                Create my workspace
            </x-button>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        /**
         * Derive the web address from the business name, until the user edits it
         * themselves.
         *
         * The "dirty" flag is the important part: once someone has typed a slug
         * we must never overwrite it, or a careful manual edit silently
         * reverts on the next keystroke in the name field.
         */
        (function () {
            const name = document.querySelector('input[name="name"]');
            const slug = document.querySelector('input[name="slug"]');

            if (!name || !slug) return;

            // Anything already in the field — typed, or restored by `old()`
            // after a validation failure — is treated as deliberate.
            let slugEdited = slug.value.trim() !== '';

            slug.addEventListener('input', () => { slugEdited = true; });

            name.addEventListener('input', () => {
                if (slugEdited) return;

                slug.value = name.value
                    .toLowerCase()
                    // NFD-decompose, then let the ASCII filter below swallow the
                    // resulting combining marks: they are non-ASCII, so they
                    // collapse into the same separator. "Café Noronha" therefore
                    // becomes "cafe-noronha" with no separate diacritic pass.
                    .normalize('NFD')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 120);
            });
        })();
    </script>
@endpush
