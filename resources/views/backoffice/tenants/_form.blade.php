{{--
    The tenant form, shared by create and edit.

    SHARED RATHER THAN DUPLICATED
    ----------------------------
    The two screens differ in three lines: the heading, the button text, and
    whether `old()` should fall back to an existing record. Everything else —
    which fields exist, what they are called, which validation message each one
    produces — must be identical, or a value would become creatable and
    un-editable in the same release. That is close to impossible to diagnose
    from a bug report, so the field list lives here once.

    `old()` precedence is handled by each field component, which reads
    `old($name, $selected)`. All this partial has to supply is the record's
    value as the fallback.
--}}

@php
    $types = []; // tenants don't have a type selector
@endphp

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">The tenant</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-text-field
            name="name"
            label="Tenant name"
            required
            :value="$tenant?->name"
            maxlength="180"
            autocomplete="off"
            placeholder="e.g. Oakwood Properties"
            hint="How you'll recognise it in lists and on leases."
        />

        <x-text-field
            name="contact_name"
            label="Contact name"
            :value="$tenant?->contact_name"
            maxlength="180"
            autocomplete="organization"
            placeholder="e.g. Jane Smith"
            hint="The person named on the lease agreement. Optional."
        />

        <x-text-field
            name="email"
            type="email"
            label="Email address"
            :value="$tenant?->email"
            maxlength="190"
            autocomplete="email"
            hint="Optional. Used for correspondence."
        />

        <x-text-field
            name="phone"
            type="tel"
            label="Phone number"
            :value="$tenant?->phone"
            maxlength="40"
            autocomplete="tel"
            hint="Optional."
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="address_line1"
                type="text"
                label="Address line 1"
                :value="$tenant?->address_line1"
                maxlength="180"
                autocomplete="address-line1"
            />

            <x-text-field
                name="address_line2"
                type="text"
                label="Address line 2"
                :value="$tenant?->address_line2"
                maxlength="180"
                autocomplete="address-line2"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="city"
                type="text"
                label="City"
                :value="$tenant?->city"
                maxlength="120"
                autocomplete="address-level2"
            />

            <x-text-field
                name="state"
                type="text"
                label="State or region"
                :value="$tenant?->state"
                maxlength="120"
                autocomplete="address-level1"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="postal_code"
                type="text"
                label="Postal code"
                :value="$tenant?->postal_code"
                maxlength="24"
                autocomplete="postal-code"
            />

            <x-text-field
                name="country"
                type="text"
                label="Country code"
                :value="$tenant?->country"
                maxlength="2"
                placeholder="US"
                autocomplete="country"
                hint="Two letters, e.g. US."
            />
        </div>

        <x-checkbox-field
            name="is_active"
            label="This tenant is in the active portfolio"
            :checked="$tenant?->is_active ?? true"
            hint="Untick to keep the record but take it out of the working list, e.g. while it is being renovated or sold."
        />
    </div>
</section>