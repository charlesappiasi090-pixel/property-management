{{--
    The property form, shared by create and edit.

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
    $types = collect($propertyTypes)->mapWithKeys(
        fn (\App\Enums\PropertyType $type): array => [$type->value => $type->label()]
    );
@endphp

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">The property</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-text-field
            name="name"
            label="Property name"
            required
            :value="$property?->name"
            maxlength="180"
            autocomplete="off"
            placeholder="e.g. Oakwood Court"
            hint="How you'll recognise it in lists and on leases. Not the same as the owner's legal name."
        />

        <x-select-field
            name="property_type"
            label="Property type"
            required
            :selected="$property?->property_type?->value ?? \App\Enums\PropertyType::APARTMENT->value"
            :options="$types"
            hint="Commercial and land have no units, so the unit list is hidden for them."
        />

        <x-textarea-field
            name="description"
            label="Description"
            :value="$property?->description"
            rows="4"
            maxlength="5000"
            hint="Anything the team needs to know: access instructions, parking, lift notes."
        />
    </div>
</section>

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">Address</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-text-field
            name="address_line1"
            label="Address line 1"
            :value="$property?->address_line1"
            maxlength="180"
            autocomplete="address-line1"
        />

        <x-text-field
            name="address_line2"
            label="Address line 2"
            :value="$property?->address_line2"
            maxlength="180"
            autocomplete="address-line2"
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="city"
                label="City"
                :value="$property?->city"
                maxlength="120"
                autocomplete="address-level2"
            />

            <x-text-field
                name="state"
                label="State or region"
                :value="$property?->state"
                maxlength="120"
                autocomplete="address-level1"
            />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="postal_code"
                label="Postal code"
                :value="$property?->postal_code"
                maxlength="24"
                autocomplete="postal-code"
            />

            <x-text-field
                name="country"
                label="Country code"
                :value="$property?->country"
                maxlength="2"
                placeholder="US"
                autocomplete="country"
                hint="Two letters, e.g. US."
            />
        </div>
    </div>
</section>

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">Ownership</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-text-field
            name="owner_name"
            label="Property owner"
            :value="$property?->owner_name"
            maxlength="180"
            hint="The beneficial owner of the asset, which is often not {{ $business->name }} itself. Optional."
        />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field
                name="owner_email"
                type="email"
                label="Owner email"
                :value="$property?->owner_email"
                maxlength="190"
                autocomplete="email"
            />

            <x-text-field
                name="owner_phone"
                type="tel"
                label="Owner phone"
                :value="$property?->owner_phone"
                maxlength="40"
                autocomplete="tel"
            />
        </div>

        <x-text-field
            name="year_built"
            type="number"
            label="Year built"
            :value="$property?->year_built"
            min="1000"
            :max="(int) now()->year + 1"
            inputmode="numeric"
            hint="Optional. Used for reporting, not for validation of anything else."
        />

        <x-checkbox-field
            name="is_active"
            label="This property is in the active portfolio"
            :checked="$property?->is_active ?? true"
            hint="Untick to keep the record but take it out of the working list, e.g. while it is being renovated or sold."
        />
    </div>
</section>
