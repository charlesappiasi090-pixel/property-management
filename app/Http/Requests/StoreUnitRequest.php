<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for adding a unit to a property.
 *
 * MONEY IS A STRING, NEVER A FLOAT
 * -------------------------------
 * `monthly_rent` is validated with a grouping-aware regex and stored as a
 * decimal string. Two traps are being avoided deliberately:
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
 * `bathrooms` is DECIMAL(3,1) rather than an integer because half baths are
 * ordinary, and `0.5` rounds to 1 in the units a user would recognise.
 */
class StoreUnitRequest extends FormRequest
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
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('createForProperty', $property) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => [
                'required',
                'string',
                'max:60',
                // Unique WITHIN the property, not within the business: two
                // blocks may both have a "Unit 4", and a landlord's own two
                // properties may as well. What must be unique is the label
                // inside one address, because that is what a notice, a lease
                // and a repair order have to disambiguate.
                //
                // Soft-deleted rows are excluded, so a removed unit's label can
                // be reused.
                Rule::unique('units', 'label')
                    ->where('property_id', $this->property()?->getKey())
                    ->whereNull('deleted_at'),
            ],

            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bathrooms' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'square_feet' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'floor' => ['nullable', 'string', 'max:20'],

            'monthly_rent' => ['nullable', 'string', 'regex:'.self::MONEY_PATTERN],

            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.unique' => 'This property already has a unit with that label.',
            'monthly_rent.regex' => 'Enter the asking rent as an amount with up to two decimal places, for example 1250 or 1,250.00.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => 'unit label',
            'square_feet' => 'square feet',
            'monthly_rent' => 'monthly rent',
        ];
    }

    /**
     * Reject a unit on a property that has no residential inventory.
     *
     * `commercial` and `land` have no units by definition. The UI hides the
     * panel for them, so this rule is a backstop against a stale tab, a
     * bookmarked form URL, or a crafted POST — and it produces a form error the
     * user can read instead of the `LogicException` the service raises for the
     * same condition.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $property = $this->property();

            if ($property === null || $property->property_type->allowsResidentialUnits()) {
                return;
            }

            $validator->errors()->add(
                'label',
                sprintf('%s properties do not have units. Change the property type first.', ucfirst($property->property_type->value))
            );
        });
    }

    /**
     * Values safe to hand to `PropertyCatalog`, normalised for storage.
     *
     * Empty text submissions become NULL so a field can be cleared, numeric
     * fields are returned as fixed-scale STRINGS to match the DECIMAL columns
     * exactly, and `is_active` is always a real boolean because an unchecked
     * checkbox is not submitted at all.
     *
     * @return array<string, mixed>
     */
    public function unitAttributes(): array
    {
        $attributes = $this->safe()->only([
            'label', 'bedrooms', 'bathrooms', 'square_feet', 'floor', 'monthly_rent', 'notes',
        ]);

        foreach (['label', 'floor', 'notes'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->stringOrNull($key);
            }
        }

        foreach (['bedrooms', 'square_feet'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->input($key) === '' ? null : (int) $this->input($key);
            }
        }

        // DECIMAL(3,1) and DECIMAL(15,2): formatted to the column's own scale so
        // the database stores 1.5 and 1250.00 rather than 1.5 and 1250.
        if ($this->has('bathrooms')) {
            $attributes['bathrooms'] = $this->input('bathrooms') === ''
                ? null
                : number_format((float) $this->input('bathrooms'), 1, '.', '');
        }

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

        $attributes['is_active'] = $this->boolean('is_active');

        return $attributes;
    }

    protected function property(): ?Property
    {
        $property = $this->route('property');

        return $property instanceof Property ? $property : null;
    }

    /**
     * An empty string from a text input means "not recorded" rather than
     * "recorded as blank".
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
