{{--
    The maintenance request form, shared by create and edit.

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
    $priorityOptions = collect(MaintenancePriority::cases())->mapWithKeys(
        fn (\App\Enums\MaintenancePriority $p): array => [$p->value => $p->label()]
    );
    $categoryOptions = collect(MaintenanceCategory::cases())->mapWithKeys(
        fn (\App\Enums\MaintenanceCategory $c): array => [$c->value => $c->label()]
    );
@endphp

<section class="ph-card">
    <div class="ph-card-header">
        <h3 class="ph-card-title">The maintenance request</h3>
    </div>

    <div class="ph-card-body space-y-5">
        <x-select-field
            name="category"
            label="Category"
            :selected=$request?->category
            :options=$categoryOptions
            hint="The type of maintenance work needed."
        />

        <x-select-field
            name="priority"
            label="Priority"
            :selected=$request?->priority
            :options=$priorityOptions
            hint="Urgency level of the request."
        />

        <x-select-field
            name="status"
            label="Status"
            :selected=$request?->status
            :options="['open' => 'Open', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled']"
            required
        />

        <x-text-field
            name="reporter_name"
            type="text"
            label="Reporter name"
            :value=$request?->reporter_name
            required
            autocomplete="off"
        />

        <x-text-field
            name="reporter_email"
            type="email"
            label="Reporter email"
            :value=$request?->reporter_email
            autocomplete="email"
            hint="Optional. The email of the person who filed the request."
        />

        <x-text-field
            name="description"
            type="text"
            label="Description"
            rows="3"
            :value=$request?->description
            required
            autocomplete="off"
            hint="What needs to be fixed, and any additional notes."
        />
    </div>
</section>