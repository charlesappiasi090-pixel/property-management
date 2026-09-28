<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a tenant.
 *
 * WHY THESE RULES LOOK THE WAY THEY DO
 * ------------------------------------
 *   - Every `max` matches the column width. A 200-character name fails here
 *     with "The name field is required" rather than as a raw SQL error that
 *     depends on the database's strict mode.
 *   - `email` uses the `email:rfc` validator, which is strict enough for
 *     production data but does not reject valid email addresses that contain
     * comments or folding whitespace.
 *   - `country` is validated as `size:2` + `alpha` so a three-letter code or
     * a typo is rejected immediately rather than being stored as a broken code.
 *   - `is_active` is a checkbox; an unchecked box is not submitted at all,
     * so the key is absent from the old input and `old()` returns null – the
     * `boolean()` method treats null as false, which is the desired behaviour.
 */
class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Tenant::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:180',
                Rule::unique('tenants', 'name')
                    ->where('business_id', $this->activeBusinessId())
                    ->whereNull('deleted_at'),
            ],

            'contact_name' => ['nullable', 'string', 'max:180'],

            'email' => ['nullable', 'string', 'email:rfc', 'max:190'],

            'phone' => ['nullable', 'string', 'max:40'],

            'address_line1' => ['nullable', 'string', 'max:180'],
            'address_line2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],

            'postal_code' => ['nullable', 'string', 'max:24'],

            // ISO 3166-1 alpha-2. `alpha` alone would accept a 3-letter code;
            // the size rule is what makes it a country rather than a continent
            // or a currency.
            'country' => ['nullable', 'string', 'size:2', 'alpha'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a tenant with this name.',
            'country.size' => 'Select a country from the list.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tenant name',
            'contact_name' => 'contact name',
            'email' => 'email address',
            'phone' => 'phone number',
            'address_line1' => 'address line 1',
            'address_line2' => 'address line 2',
            'postal_code' => 'postal code',
            'country' => 'country code',
        ];
    }

    /**
     * Values safe to hand to `PropertyCatalog`, normalised for storage.
     *
     * Empty text submissions become NULL so a field can be cleared, and
     * `is_active` is always a real boolean because an unchecked checkbox is
     * not submitted at all.
     *
     * @return array<string, mixed>
     */
    public function tenantAttributes(): array
    {
        $attributes = $this->safe()->only([
            'name', 'contact_name', 'email',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
        ]);

        foreach (['contact_name', 'email', 'address_line1', 'address_line2', 'city', 'state', 'postal_code'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        if ($this->has('country')) {
            $attributes['country'] = strtoupper((string) $this->input('country'));
        }

        $attributes['is_active'] = $this->boolean('is_active');

        return $attributes;
    }

    protected function activeBusinessId(): ?int
    {
        return app(\App\Support\Tenancy\BusinessContext::class)->id();
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