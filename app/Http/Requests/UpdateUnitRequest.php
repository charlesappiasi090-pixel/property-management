<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a unit.
 *
 * Extends `StoreUnitRequest` so the money handling, the decimal formatting and
 * the "no units on commercial/land" rule cannot drift between the two forms —
 * a rent that is accepted when creating a unit and rejected when editing one
 * reads as a bug in the application rather than in the form.
 *
 * Two things genuinely differ from the store form:
 *   - the ability is `update` on the unit, not `createForProperty`;
 *   - the label-uniqueness rule must ignore the unit being edited, or saving a
 *     unit without touching its label would fail every time.
 */
class UpdateUnitRequest extends StoreUnitRequest
{
    public function authorize(): bool
    {
        $unit = $this->unit();

        return $unit !== null && ($this->user()?->can('update', $unit) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['label'] = [
            'required',
            'string',
            'max:60',
            Rule::unique('units', 'label')
                ->where('property_id', $this->property()?->getKey())
                ->whereNull('deleted_at')
                ->ignore($this->unit()),
        ];

        /*
         * The parent rule's `withValidator()` backstop still runs and still
         * refuses commercial/land. For an EXISTING unit on a property that has
         * since been retyped, the error is correct: the user cannot save this
         * unit while the property is a car park. It is a state the
         * retyping rule in `UpdatePropertyRequest` prevents from being created
         * in the first place, and this keeps it correct if the data arrived
         * by import.
         */

        return $rules;
    }

    protected function unit(): ?Unit
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit ? $unit : null;
    }
}
