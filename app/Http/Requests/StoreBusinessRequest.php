<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a business during onboarding.
 *
 * WHY A FormRequest AND NOT AN INLINE `validate()`
 * -----------------------------------------------
 * Rules are only half of a form request. It also:
 *   - authorises the request up front (see `authorize()`),
 *   - centralises the attribute labels so error messages read
 *     "The business name is required" rather than "The name field is required",
 *   - gives the controller a typed, pre-validated DTO.
 *
 * The rules also encode real business constraints, not just "required":
 *   - `name` max 180 matches the column, so a 200-character name fails at
 *     validation rather than as a raw SQL error.
 *   - `slug` is validated for format and uniqueness against soft-deleted rows
 *     too, because a deleted business's slug is still taken.
 */
class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A signed-in user may always create a business for themselves. The
        // reason this is not `true` blindly: `authorize()` returning false
        // yields a 403 before validation, which is the correct semantics if a
        // plan limit on "businesses per user" is ever introduced. The
        // subscription quota that governs the CHILD resources (properties,
        // staff, ...) is enforced later by PlanQuota.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:180'],

            'slug' => [
                'nullable',
                'string',
                'lowercase',
                // Mirrors Str::slug() output so a hand-typed slug cannot
                // contain characters that break the URL later.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'max:120',
                /*
                 * Soft-deleted businesses are INCLUDED, which is what we want:
                 * a deleted business keeps holding its slug, so reusing it
                 * would create two businesses with the same public identifier.
                 *
                 * That is already the default. `Rule::unique()` resolves the
                 * table NAME and asks the presence verifier for a count, so no
                 * Eloquent global scope is involved and the SoftDeletes scope
                 * never filters anything out. `withoutTrashed()` would opt OUT
                 * of that.
                 *
                 * There is deliberately no `->withTrashed()` here: that method
                 * does not exist on `Illuminate\Validation\Rules\Unique` (only
                 * `withoutTrashed()` and `onlyTrashed()` do), so calling it
                 * raised a fatal `Error` — not a validation failure — on every
                 * single onboarding submission, taking the whole signup flow
                 * down with a 500.
                 */
                Rule::unique('businesses', 'slug'),
            ],

            'legal_name' => ['nullable', 'string', 'max:180'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],

            'address_line1' => ['nullable', 'string', 'max:180'],
            'address_line2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:24'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],

            'timezone' => ['nullable', 'string', 'timezone'],
            'locale' => ['nullable', 'string', 'max:12'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],

            'plan' => [
                'required',
                'string',
                // Resolve the plan by code, but only among plans that are
                // actually purchasable — prevents a client posting a code
                // for a hidden or retired plan.
                Rule::exists('plans', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The web address may only contain lowercase letters, numbers and single hyphens.',
            'country.size' => 'Select a country from the list.',
            'plan.exists' => 'Please choose one of the available plans.',
            'timezone.timezone' => 'Select a valid timezone.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'business name',
            'slug' => 'web address',
            'legal_name' => 'legal business name',
            'tax_id' => 'tax ID',
            'address_line1' => 'address line 1',
            'address_line2' => 'address line 2',
            'postal_code' => 'postal code',
            'rent_due_day_of_month' => 'rent due day',
        ];
    }

    /**
     * Values safe to persist, with the slug resolved.
     *
     * @return array<string, mixed>
     */
    public function businessAttributes(): array
    {
        return $this->safe()->only([
            'name', 'slug', 'legal_name', 'tax_id', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'state', 'postal_code',
            'country', 'timezone', 'locale', 'currency',
        ]);
    }
}
