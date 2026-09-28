{{--
    The lease form, shared by create and edit.

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
    $moneyPattern = '/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/;
@endphp

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">The lease</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-select-field
            name="property_id"
            label="Property"
            :selected="$lease?->property_id"
            :options="$propertyOptions"
            hint="The property this lease covers. Required."
        />

        @if ($lease && $lease->unit_id)
            <x-select-field
                name="unit_id"
                label="Unit"
                :selected="$lease?->unit_id"
                :options="$unitOptions"
                hint="The specific unit within the property (optional)."
            />
        @endif

        <x-select-field
            name="tenant_id"
            label="Tenant"
            :selected="$lease?->tenant_id"
            :options="$tenantOptions"
            hint="The tenant associated with this lease. Required."
        />

        <x-text-field
            name="start_date"
            type="date"
            label="Start date"
            :value=$lease?->start_date
            required
            autocomplete="off"
        />

        <x-text-field
            name="end_date"
            type="date"
            label="End date"
            :value=$lease?->end_date
            autocomplete="off"
            hint="Leave blank for a periodic lease."
        />

        <x-text-field
            name="monthly_rent"
            label="Monthly rent"
            :value=$lease?->monthly_rent
            autocomplete="off"
            placeholder="e.g. 1250 or 1,250.00"
            hint="Asking rent. Format: number or number with thousand separator and up to two decimal places."
        />

        <x-text-field
            name="security_deposit"
            type="text"
            label="Security deposit"
            :value=$lease?->security_deposit
            autocomplete="off"
            placeholder="e.g. 0 or 1,500.00"
            hint="Optional. Format same as monthly rent."
        />

        <x-select-field
            name="status"
            label="Status"
            :selected=$lease?->status
            :options="['active' => 'Active', 'terminated' => 'Terminated']"
            required
        />
    </div>
</section>