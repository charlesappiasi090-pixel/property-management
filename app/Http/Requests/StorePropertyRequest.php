<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\Property;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a property.
 *
 * `UpdatePropertyRequest` extends this and overrides only the two things that
 * genuinely differ: the ability being checked, and the name-uniqueness rule,
 * which must ignore the record being edited.
 *
 * WHY THESE RULES LOOK THE WAY THEY DO
 * ------------------------------------
 *   - Every `max` matches its column width. A 400-character city fails here
 *     with "The city may not be greater than 120 characters" rather than as a
 *     raw SQL truncation error that depends on the database's strict mode.
 *   - `name` is unique PER BUSINESS, not globally. Two different landlords may
 *     both own a property called "Flat 1"; one landlord may not own two.
 *   - Soft-deleted rows are excluded from that uniqueness check. A property
 *     removed in 2019 is not "still taking the name" the way a live one is, and
 *     a `->withoutTrashed()` here would make the name unrecoverable forever.
 *   - `property_type` is validated against the enum, so a value added to
 *     `PropertyType` becomes valid automatically instead of needing this file
 *     edited.
 */
class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
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
                Rule::unique('properties', 'name')
                    ->where('business_id', $this->activeBusinessId())
                    ->whereNull('deleted_at'),
            ],

            'property_type' => ['required', Rule::enum(PropertyType::class)],

            'description' => ['nullable', 'string', 'max:5000'],

            'address_line1' => ['nullable', 'string', 'max:180'],
            'address_line2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:24'],

            // ISO 3166-1 alpha-2. `alpha` alone would accept a 3-letter code;
            // the size rule is what makes it a country rather than a continent
            // or a currency.
            'country' => ['nullable', 'string', 'size:2', 'alpha'],

            'owner_name' => ['nullable', 'string', 'max:180'],
            'owner_email' => ['nullable', 'string', 'email:rfc', 'max:190'],
            'owner_phone' => ['nullable', 'string', 'max:40'],

            // The upper bound is NEXT year rather than this one: a development
            // handed over in December is completed for occupancy in January.
            'year_built' => ['nullable', 'integer', 'min:1000', 'max:'.((int) now()->year + 1)],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a property with this name.',
            'country.size' => 'Select a country from the list.',
            'year_built.max' => 'The year built cannot be in the future.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'property name',
            'property_type' => 'property type',
            'address_line1' => 'address line 1',
            'address_line2' => 'address line 2',
            'postal_code' => 'postal code',
            'owner_name' => 'owner name',
            'owner_email' => 'owner email',
            'owner_phone' => 'owner phone',
            'year_built' => 'year built',
        ];
    }

    /**
     * Values safe to hand to `PropertyCatalog`, normalised for storage.
     *
     * Three normalisations happen here rather than in the model, because they
     * are about the SUBMITTED STRING and not about the record:
     *
     *   - `country` is upper-cased, so a form posting "us" and an import
     *     writing "US" cannot produce two spellings of the same country.
     *   - Text fields are trimmed, and an empty submission becomes NULL. An
     *     empty string from a text input means "not recorded" — the user
     *     clearing a field must be able to clear it — and '' would otherwise
     *     render as a blank line in an address block that should skip it.
     *   - `is_active` becomes a real boolean. An unchecked checkbox is not
     *     submitted at all, so reading it as null would store NULL, and
     *     `is_active` is NOT NULL.
     *
     * A field that is ABSENT is left out entirely rather than sent as null, so
     * a partial payload cannot blank columns the caller never mentioned. The
     * one exception is `is_active`, where absence genuinely means "unticked".
     *
     * @return array<string, mixed>
     */
    public function propertyAttributes(): array
    {
        $attributes = $this->safe()->only([
            'name', 'property_type', 'description',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country',
            'owner_name', 'owner_email', 'owner_phone', 'year_built',
        ]);

        $cleared = [
            'description',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'postal_code',
            'country',
            'owner_name',
            'owner_email',
            'owner_phone',
        ];

        foreach ($cleared as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        if ($this->has('year_built')) {
            $attributes['year_built'] = $this->input('year_built') === ''
                ? null
                : (int) $this->input('year_built');
        }

        if ($attributes['country'] ?? null) {
            $attributes['country'] = strtoupper((string) $attributes['country']);
        }

        $attributes['is_active'] = $this->boolean('is_active');

        return $attributes;
    }

    /**
     * The tenant the uniqueness rule is scoped to.
     *
     * Read from the resolved context, not from the request: a form cannot
     * choose which business its uniqueness is measured against.
     */
    protected function activeBusinessId(): ?int
    {
        return app(BusinessContext::class)->id();
    }

    /**
     * An empty string from a text input means "not recorded", not "recorded as
     * blank". Left as '' it would render as an empty cell in an address block
     * that should have skipped the line entirely.
     */
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
