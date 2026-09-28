<?php

namespace App\Http\Requests;

use App\Models\Lease;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a lease.
 *
 * Extends `LeaseRequest` rather than repeating it, because the only two
 * differences are real: the ability is `update` on the lease, not `create`,

 * and the uniqueness/date rules must ignore the lease being edited.
 */
class UpdateLeaseRequest extends LeaseRequest
{
    public function authorize(): bool
    {
        $lease = $this->lease();

        return $lease instanceof Lease && ($this->user()?->can('update', $lease) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /*
         * The parent rule's `withValidator()` backstop still runs and still
         * refuses commercial/land. For an EXISTING lease on a property that
         * has since been retyped, the error is correct: the user cannot save
         * this lease while the property is a car park. It is a state the
         * retyping rule in `UpdatePropertyRequest` prevents from being created
         * in the first place, and this keeps it correct if the data arrived
         * by import.
         */

        /*
         * `ignore($lease)` is what stops the edit form rejecting its own
         * current start date as invalid (if the lease is periodic, end_date
         * is null, etc.).
         */
        $rules['start_date'] = [
            'required',
            'date',
            // Allow the same start date when editing (otherwise saving without
            * touching the date would fail every time).
            Rule::date()->skipOnUpdating(),
        ];

        $rules['end_date'] = [
            'nullable',
            'date',
            // When editing, ignore the existing end_date for the "after start_date"
            * check so the user can clear it or leave it unchanged.
            Rule::date()->skipOnUpdating(),
        ];

        /*
         * The status may be changed, so we re-validate the enum here.
         */
        $rules['status'] = ['required', 'in:active,terminated'];

        return $rules;
    }

    protected function lease(): ?Lease
    {
        $lease = $this->route('lease');

        return $lease instanceof Lease ? $lease : null;
    }
}