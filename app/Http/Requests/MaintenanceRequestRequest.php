<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a maintenance request.
 *
 * CATEGORY IS AN ENUM
 * -----------------
 * `category` is validated against `MaintenanceCategory::class` so that a value
 * added to the enum becomes valid automatically instead of needing this file
 * edited.  The enum also provides the `label()` method for nice UI display.
 *
 * PRIORITY IS AN ENUM
 * -------------------
 * `priority` is validated against `MaintenancePriority::class` for the same
 * reason as category.
 *
 * STATUS IS AN ENUM
 * ----------------
 * `status` is validated against the four allowed values.  The default is
 * `open`, so a new request is always open unless the form pre‑selects
 * something else.
 *
 * REPORTER NAME/EMAIL
 * -------------------
 * `reporter_name` is required – a maintenance request must always have a name.
 * `reporter_email` is optional but validated as an email when provided.
 *
 * DESCRIPTION
 * ------------
 * `description` is required and limited to 1000 characters.
 *
 * PRIORITY
 * ----------
 * `priority` is validated against the enum and is required.
 */
class MaintenanceRequestRequest extends FormRequest
{
    protected const CATEGORY_PATTERN = MaintenanceCategory::class;
    protected const PRIORITY_PATTERN = MaintenancePriority::class;

    public function authorize(): bool
    {
        $property = $this->property();

        return $property instanceof \App\Models\Property && ($this->user()?->can('create', MaintenanceRequest::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'exists:properties,id',
                // Scope the property to the active business – the route model
                * binding already does this, but a malicious caller could
                * craft a POST with a random property id.
                'whereHas', function ($q) {
                    $q->where('business_id', app(BusinessContext::class)->id());
                },
            ],

            'lease_id' => ['nullable', 'exists:leases,id'],

            'unit_id' => ['nullable', 'exists:units,id'],

            'tenant_id' => ['nullable', 'exists:tenants,id'],

            'category' => ['required', Rule::enum(MaintenanceCategory::class)],

            'priority' => ['required', Rule::enum(MaintenancePriority::class)],

            'status' => ['required', 'in:open,in_progress,completed,cancelled'],

            'reporter_name' => ['required', 'string', 'max:180'],

            'reporter_email' => ['nullable', 'string', 'email', 'max:190'],

            'description' => ['required', 'string', 'max:1000'],

            // Optional free‑text field – no special validation beyond max length.
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'Select a valid maintenance category.',
            'priority.in' => 'Select a valid priority level.',
            'reporter_name.required' => 'A reporter name is required.',
            'reporter_email.email' => 'Please enter a valid email address.',
            'description.required' => 'A description is required.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'property_id' => 'property',
            'lease_id' => 'lease',
            'unit_id' => 'unit',
            'tenant_id' => 'tenant',
            'category' => 'category',
            'priority' => 'priority',
            'status' => 'status',
            'reporter_name' => 'reporter name',
            'reporter_email' => 'reporter email',
            'description' => 'description',
        ];
    }

    /**
     * Values safe to hand to `MaintenanceRequest`, normalised for storage.
     *
     * Text fields are trimmed, and an empty submission becomes NULL so a field
     * can be cleared.  Enum fields are returned as their backing string value.
     *
     * @return array<string, mixed>
     */
    public function maintenanceRequestAttributes(): array
    {
        $attributes = $this->safe()->only([
            'property_id', 'lease_id', 'unit_id', 'tenant_id',
            'category', 'priority', 'status',
            'reporter_name', 'reporter_email', 'description',
        ]);

        // Enum fields: return the backing string value.
        foreach (['category', 'priority', 'status'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->input($key);
            }
        }

        // Text fields: trim and possibly clear to null.
        foreach (['reporter_email', 'description'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        // reporter_name is always present (validated as required), but we
        // normalise it the same way as the other text fields for consistency.
        $attributes['reporter_name'] = $this->stringOrNull('reporter_name');

        return $attributes;
    }

    protected function property(): ?\App\Models\Property
    {
        $property = $this->route('property');

        return $property instanceof \App\Models\Property ? $property : null;
    }

    protected function activeBusinessId(): ?int
    {
        return app(BusinessContext::class)->id();
    }

    protected function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}