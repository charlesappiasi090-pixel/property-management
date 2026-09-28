@extends('layouts.app')

@section('title', 'Business settings')

@php
    // `$business` is the active tenant, supplied by the controller. The
    // question is asked about it explicitly rather than through spatie's
    // ambient team id — see AppServiceProvider::registerAuthorizationDirectives().
    $canManage = $business !== null
        && (auth()->user()?->canInBusiness(\App\Enums\PermissionName::SETTINGS_MANAGE, $business) ?? false);
@endphp

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Business settings</h2>
        <p class="mt-1 text-sm text-slate-600">
            This information appears on your dashboard, in tenant emails, and on receipts.
        </p>
    </div>

    @unless ($canManage)
        <div class="mb-6 ph-alert ph-alert-info">
            <x-icon name="information-circle" class="size-5 shrink-0" />
            <p class="text-sm">
                You can view these settings, but only an owner can change them.
            </p>
        </div>
    @endunless

    <form
        method="POST"
        action="{{ route('app.settings.business.update') }}"
        class="space-y-6"
    >
        @csrf
        @method('PUT')

        <x-flash-messages />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- ---------------------------------------------------------------- --}}
                {{-- Identity                                                          --}}
                {{-- ---------------------------------------------------------------- --}}
                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Identity</h3>
                    </div>

                    <div class="ph-card-body grid gap-5 sm:grid-cols-2">
                        <x-text-field
                            name="name"
                            label="Business name"
                            :value="$business->name"
                            required
                            :disabled="! $canManage"
                            autocomplete="organization"
                        />

                        <x-text-field
                            name="slug"
                            label="Web address"
                            :value="$business->slug"
                            :disabled="! $canManage"
                            hint="Lowercase letters, numbers and single hyphens."
                        />

                        <x-text-field
                            name="legal_name"
                            label="Legal name"
                            :value="$business->legal_name"
                            :disabled="! $canManage"
                            hint="Registered entity name, if different. Used on receipts."
                        />

                        <x-text-field
                            name="tax_id"
                            label="Tax ID"
                            :value="$business->tax_id"
                            :disabled="! $canManage"
                            hint="VAT, ABN, EIN — whatever your jurisdiction uses."
                        />
                    </div>
                </section>

                {{-- ---------------------------------------------------------------- --}}
                {{-- Contact                                                           --}}
                {{-- ---------------------------------------------------------------- --}}
                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Contact</h3>
                    </div>

                    <div class="ph-card-body grid gap-5 sm:grid-cols-2">
                        <x-text-field
                            name="email"
                            type="email"
                            label="Business email"
                            :value="$business->email"
                            :disabled="! $canManage"
                            autocomplete="email"
                        />

                        <x-text-field
                            name="phone"
                            type="tel"
                            label="Phone"
                            :value="$business->phone"
                            :disabled="! $canManage"
                            autocomplete="tel"
                        />
                    </div>
                </section>

                {{-- ---------------------------------------------------------------- --}}
                {{-- Address                                                           --}}
                {{-- ---------------------------------------------------------------- --}}
                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Address</h3>
                    </div>

                    <div class="ph-card-body grid gap-5 sm:grid-cols-2">
                        <x-text-field
                            name="address_line1"
                            label="Address line 1"
                            :value="$business->address_line1"
                            :disabled="! $canManage"
                            autocomplete="address-line1"
                            class="sm:col-span-2"
                        />

                        <x-text-field
                            name="address_line2"
                            label="Address line 2"
                            :value="$business->address_line2"
                            :disabled="! $canManage"
                            autocomplete="address-line2"
                            class="sm:col-span-2"
                        />

                        <x-text-field
                            name="city"
                            label="City"
                            :value="$business->city"
                            :disabled="! $canManage"
                            autocomplete="address-level2"
                        />

                        <x-text-field
                            name="state"
                            label="State / region"
                            :value="$business->state"
                            :disabled="! $canManage"
                            autocomplete="address-level1"
                        />

                        <x-text-field
                            name="postal_code"
                            label="Postal code"
                            :value="$business->postal_code"
                            :disabled="! $canManage"
                            autocomplete="postal-code"
                        />

                        <x-text-field
                            name="country"
                            label="Country code"
                            :value="$business->country"
                            :disabled="! $canManage"
                            maxlength="2"
                            placeholder="US"
                            hint="Two-letter code, e.g. US, GB, NG."
                            autocomplete="country"
                        />
                    </div>
                </section>
            </div>

            {{-- ---------------------------------------------------------------- --}}
            {{-- Regional settings                                                 --}}
            {{-- ---------------------------------------------------------------- --}}
            <div class="space-y-6">
                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Regional</h3>
                    </div>

                    <div class="ph-card-body space-y-5">
                        {{--
                            Timezone and currency are selects, not free text, so
                            the value stored is always one the app understands.
                            A bad timezone silently shifts every rent-due date
                            calculation, and a 3-letter currency that is not a
                            real code produces nonsense on every receipt.
                        --}}
                        <x-select-field
                            name="timezone"
                            label="Timezone"
                            :selected="$business->timezone ?? config('propertyhub.timezone')"
                            :options="collect(\DateTimeZone::listIdentifiers())
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])"
                            :disabled="! $canManage"
                            placeholder="Select a timezone"
                            hint="Used to decide when rent is due."
                        />

                        <x-select-field
                            name="currency"
                            label="Currency"
                            :selected="$business->currency ?? config('propertyhub.currency')"
                            :options="[
                                'USD' => 'USD — US Dollar',
                                'EUR' => 'EUR — Euro',
                                'GBP' => 'GBP — British Pound',
                                'CAD' => 'CAD — Canadian Dollar',
                                'AUD' => 'AUD — Australian Dollar',
                                'ZAR' => 'ZAR — South African Rand',
                                'NGN' => 'NGN — Nigerian Naira',
                                'KES' => 'KES — Kenyan Shilling',
                                'GHS' => 'GHS — Ghanaian Cedi',
                                'INR' => 'INR — Indian Rupee',
                                'JPY' => 'JPY — Japanese Yen',
                                'BRL' => 'BRL — Brazilian Real',
                            ]"
                            :disabled="! $canManage"
                            placeholder="Select a currency"
                        />
                    </div>
                </section>

                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Account</h3>
                    </div>

                    <dl class="ph-card-body space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Status</dt>
                            <dd><x-status-badge :status="$business->status" /></dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="font-medium text-slate-900">
                                {{ $business->created_at?->format('j M Y') }}
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Members</dt>
                            <dd class="font-medium text-slate-900 tabular-nums">
                                {{ $business->members()->count() }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Save                                                               --}}
        {{-- ---------------------------------------------------------------- --}}
        @if ($canManage)
            <div class="sticky bottom-0 -mx-4 flex justify-end gap-3 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
                <x-button type="submit" loading-text="Saving...">Save changes</x-button>
            </div>
        @endif
    </form>
@endsection
