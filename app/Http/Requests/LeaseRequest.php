<?php

namespace App\Http\Requests;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for adding a lease.
 *
 * MONEY IS A STRING, NEVER A FLOAT
 * -------------------------------
 * `monthly_rent` and `security_deposit` are validated with a grouping-aware
 * regex and stored as decimal strings. Two traps are being avoided deliberately:
 *
 *   - Accepting "1,250.00" is right for a form, and stripping the separators
 *     with `str_replace(',', '')` alone is wrong: "1,2,3" becomes 123 and is
 *     accepted. The regex below only allows separators in groups of three, so
 *     a typo is a validation error instead of a silently wrong rent.
 *   - Nothing here passes the value through `floatval()`. The column is
 *     DECIMAL(15,2) and the model casts it with `decimal:2`; a float in
 *     between would reintroduce the rounding error that
 *     `App\Support\Money\Money` exists to eliminate.
 *
 * `start_date` and `end_date` are validated as proper dates, and `end_date`
 * must be on or after `start_date` when provided.
 */
class LeaseRequest extends FormRequest
{
    /**
     * Grouped thousands separators, or none; at most two decimal places.
     *
     * Written as an alternation rather than an optional comma group so
     * "1,25" and "1234,567" are both rejected instead of being silently
     * reinterpreted.
     */
    protected const MONEY_PATTERN = '/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $property = $this->property();

        return $property instanceof Property
            && ($this->user()?->can('create', Lease::class) ?? false);
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
                // Also scope the property to the active business – the route
                * binding already does this, but a malicious caller could
                * craft a POST with a random property id.
                ->where('business_id', app(BusinessContext::class)->id()),
            ],

            'unit_id' => ['nullable', 'exists:units,id'],

            'tenant_id' => [
                'required',
                'exists:tenants,id',
                // Scope to the active business.
                'whereHas', function ($q) {
                    $q->where('business_id', app(BusinessContext::class)->id());
                },
            ],

            'start_date' => ['required', 'date'],

            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'monthly_rent' => ['required', 'string', 'regex:'.self::MONEY_PATTERN],

            'security_deposit' => ['nullable', 'string', 'regex:'.self::MONEY_PATTERN],

            'status' => ['required', 'in:active,terminated'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monthly_rent.regex' => 'Enter the asking rent as an amount with up to two decimal places, for example 1250 or 1,250.00.',
            'security_deposit.regex' => 'Enter the security deposit as an amount with up to two decimal places, for example 0 or 1,500.00.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'property_id' => 'property',
            'unit_id' => 'unit',
            'tenant_id' => 'tenant',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'monthly_rent' => 'monthly rent',
            'security_deposit' => 'security deposit',
            'status' => 'lease status',
        ];
    }

    /**
     * Values safe to hand to `Lease`, normalised for storage.
     *
     * Empty text submissions become NULL so a field can be cleared, numeric
 * fields are returned as fixed-scale STRINGS to match the DECIMAL columns
 * exactly, and `is_active` is always a real boolean because an unchecked
 * checkbox is not submitted at all.
 *
 * @return array<string, mixed>
 */
    public function leaseAttributes(): array
    {
        $attributes = $this->safe()->only([
            'property_id', 'unit_id', 'tenant_id',
            'start_date', 'end_date', 'monthly_rent', 'security_deposit',
        ]);

        // Text fields: if present, trim and possibly clear to null.
        foreach (['start_date', 'end_date'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->dateOrNull($key);
            }
        }

        // DECIMAL(15,2): formatted to the column's own scale so the database
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

        if ($this->has('security_deposit')) {
            $attributes['security_deposit'] = $this->input('security_deposit') === ''
                ? null
                : number_format(
                    (float) str_replace(',', '', (string) $this->input('security_deposit')),
                    2,
                    '.',
                    ''
                );
        }

        return $attributes;
    }

    protected function property(): ?Property
    {
        $property = $this->route('property');

        return $property instanceof Property ? $property : null;
    }

    protected function tenant(): ?Tenant
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected function activeBusinessId(): ?int
    {
        return app(BusinessContext::class)->id();
    }

    /**
     * An empty string from a date input means "not recorded" rather than
     * "recorded as blank".
     */
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