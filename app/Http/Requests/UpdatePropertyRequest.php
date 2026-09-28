<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a property.
 *
 * Extends `StorePropertyRequest` rather than repeating it, because the only two
 * differences are real: the ability being checked, and the name-uniqueness
 * rule, which must ignore the record being edited. Two copies of the other
 * twenty-odd rules would drift — and a rule that drifted in only the update
 * form would make a value un-editable into a value un-creatable, which is
 * close to impossible to diagnose from a bug report.
 *
 * `authorize()` passes the property from the route. Laravel resolves the model
 * through the `properties` route binding, which runs the `BelongsToBusiness`
 * global scope, so a foreign id never reaches the policy as an object — the
 * user gets a 404 from the binder.
 */
class UpdatePropertyRequest extends StorePropertyRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('update', $property) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /*
         * `ignore($property)` is what stops the edit form rejecting its own
         * current name as a duplicate. Without it, saving a property without
         * touching its name fails validation every single time.
         *
         * `ignore()` also supplies the `business_id` scope, because the default
         * is the primary key column; the parent rule's `where('business_id')`
         * still applies and keeps the comparison inside one tenant.
         */
        $rules['name'] = [
            'required',
            'string',
            'min:2',
            'max:180',
            Rule::unique('properties', 'name')
                ->where('business_id', $this->activeBusinessId())
                ->whereNull('deleted_at')
                ->ignore($this->route('property')),
        ];

        /*
         * The type may be CHANGED, and one change is refused: a property that
         * already has units cannot be retyped as `commercial` or `land`, whose
         * unit panel the UI hides. The units would still be there, invisible
         * from the property page, which is how inventory goes missing.
         *
         * Checked with a closure rather than a database rule because the
         * condition spans two tables and the enum. `land` is not blocked for a
         * property with units so much as it is unreachable — see below.
         */
        $rules['property_type'] = [
            'required',
            Rule::enum(PropertyType::class),
            function (string $attribute, mixed $value, \Closure $fail): void {
                $property = $this->route('property');

                if (! $property instanceof Property || ! is_string($value)) {
                    return;
                }

                $type = PropertyType::from($value);

                if ($type->allowsResidentialUnits() || $property->units()->count() === 0) {
                    return;
                }

                $fail('This property has units, so its type cannot be changed to one that has no residential inventory. Remove or move the units first.');
            },
        ];

        return $rules;
    }
}
