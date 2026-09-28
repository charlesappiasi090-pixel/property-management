<?php

namespace App\Http\Requests;

use App\Models\RentRoll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a rent‑roll snapshot.
 *
 * The snapshot is taken for a specific property and date.  The request
 * validates that the property exists and belongs to the active business,
 * and that the snapshot date is not in the far future.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->property();

        return $property instanceof \App\Models\Property && ($this->user()?->can('create', \App\Models\RentRoll::class) ?? false);
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

            'snapshot_at' => ['required', 'date', 'before_or_equal:'.now()->addYears(1)],

            'tenant_id' => ['nullable', 'exists:tenants,id'],

            'lease_id' => ['nullable', 'exists:leases,id'],

            'tenant_name' => ['nullable', 'string', 'max:180'],

            'unit_label' => ['nullable', 'string', 'max:60'],

            'lease_start_date' => ['nullable', 'date'],

            'lease_end_date' => ['nullable', 'date', 'after_or_equal:lease_start_date'],

            'monthly_rent' => ['nullable', 'string', 'regex:'.\App\Http\Requests\PaymentRequest::MONEY_PATTERN],

            'lease_status' => ['required', 'in:active,terminated'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'snapshot_at.before_or_equal' => 'The snapshot date must not be more than a year in the future.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'property_id' => 'property',
            'snapshot_at' => 'snapshot date',
            'tenant_id' => 'tenant',
            'lease_id' => 'lease',
            'tenant_name' => 'tenant name',
            'unit_label' => 'unit label',
            'lease_start_date' => 'lease start date',
            'lease_end_date' => 'lease end date',
            'monthly_rent' => 'monthly rent',
            'lease_status' => 'lease status',
        ];
    }

    /**
     * Values safe to hand to `RentRoll`, normalised for storage.
     *
     * Text fields are trimmed, and an empty submission becomes NULL so a field
     * can be cleared.  Decimal fields are returned as fixed‑scale STRINGS to
     * match the DECIMAL columns exactly.
     *
     * @return array<string, mixed>
     */
    public function rentRollAttributes(): array
    {
        $attributes = $this->safe()->only([
            'property_id', 'tenant_id', 'lease_id',
            'tenant_name', 'unit_label', 'snapshot_at',
            'lease_start_date', 'lease_end_date', 'monthly_rent', 'lease_status',
        ]);

        // Text fields: if present, trim and possibly clear to null.
        foreach (['tenant_name', 'unit_label'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        // DATE fields: if present, parse; if empty, NULL.
        foreach (['snapshot_at', 'lease_start_date', 'lease_end_date'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->dateOrNull($key);
            }
        }

        // MONEY field: formatted to the column's own scale so the database
        // stores 1250.00 rather than 1250.
        if ($this->has('monthly_rent')) {
            $attributes['monthly_rent'] = $this->input('monthly_rent') === ''
                ? null
                : number_format(
                    (float) str_replace(',', '', (string) $this->input('monthly_rent')),
                    2,
                    '.',
                    ''
                );
        }

        // ENUM field: return the backing string value.
        if ($this->has('lease_status')) {
            $attributes['lease_status'] = $this->input('lease_status');
        }

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

    protected function dateOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}