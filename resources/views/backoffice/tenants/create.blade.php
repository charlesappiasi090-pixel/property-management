@extends('layouts.app')

@section('title', 'Add a tenant')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.tenants.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to tenants
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">Add a tenant</h2>
            <p class="mt-1 text-sm text-slate-600">
                An external landlord or rental entity that will be associated with
                leases and activity logs.
            </p>
        </div>

        <form method="POST" action="{{ route('app.tenants.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <div class="ph-card">
                <div class="ph-card-body space-y-5">
                    <x-text-field
                        name="name"
                        type="text"
                        label="Tenant name"
                        required
                        autocomplete="off"
                        placeholder="e.g. Oakwood Properties"
                        hint="How you'll recognise it in lists and on leases."
                    />

                    <x-text-field
                        name="contact_name"
                        type="text"
                        label="Contact name"
                        autocomplete="organization"
                        placeholder="e.g. Jane Smith"
                        hint="The person named on the lease agreement. Optional."
                    />

                    <x-text-field
                        name="email"
                        type="email"
                        label="Email address"
                        autocomplete="email"
                        placeholder="contact@oakwood.example"
                        hint="Optional. Used for correspondence."
                    />

                    <x-text-field
                        name="phone"
                        type="tel"
                        label="Phone number"
                        autocomplete="tel"
                        placeholder="+1 (555) 123-4567"
                        hint="Optional."
                    />

                    <x-text-field
                        name="address_line1"
                        type="text"
                        label="Address line 1"
                        autocomplete="address-line1"
                        hint="Optional."
                    />

                    <x-text-field
                        name="address_line2"
                        type="text"
                        label="Address line 2"
                        autocomplete="address-line2"
                        hint="Optional."
                    />

                    <x-text-field
                        name="city"
                        type="text"
                        label="City"
                        autocomplete="address-level2"
                        hint="Optional."
                    />

                    <x-text-field
                        name="state"
                        type="text"
                        label="State or region"
                        autocomplete="address-level1"
                        hint="Optional."
                    />

                    <x-text-field
                        name="postal_code"
                        type="text"
                        label="Postal code"
                        autocomplete="postal-code"
                        hint="Optional."
                    />

                    <x-text-field
                        name="country"
                        type="text"
                        label="Country code"
                        maxlength="2"
                        placeholder="US"
                        autocomplete="country"
                        hint="Two letters, e.g. US."
                    />

                    <x-checkbox-field
                        name="is_active"
                        label="This tenant is in the active portfolio"
                        :checked="true"
                        hint="Untick to keep the record but take it out of the working list."
                    />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.tenants.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Adding...">
                    <x-icon name="plus" class="size-4" />
                    Add tenant
                </x-button>
            </div>
        </form>
    </div>
@endsection