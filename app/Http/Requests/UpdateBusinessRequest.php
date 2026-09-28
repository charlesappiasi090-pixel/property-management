<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing the active business's profile.
 *
 * The `slug` uniqueness rule is scoped with `ignore($this->route('business'))`
 * — but the business id is never taken from the route in this application
 * (the active tenant comes from middleware), so the ignore target is resolved
 * from the resolved context. That removes any chance of a user editing a
 * different business by manipulating a URL.
 */
class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // The route also carries `permission:settings.manage`; this is the
        // second, independent check. Belt-and-braces on a settings screen that
        // controls tenant-wide identity is worth the two lines.
        //
        // The business is named explicitly so this cannot be satisfied by a
        // `settings.manage` permission held in a different workspace.
        return $user->canInBusiness(
            \App\Enums\PermissionName::SETTINGS_MANAGE,
            app(\App\Support\Tenancy\BusinessContext::class)->business()
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $business = app(\App\Support\Tenancy\BusinessContext::class)->business();

        return [
            'name' => ['required', 'string', 'min:2', 'max:180'],

            'slug' => [
                'nullable',
                'string',
                'lowercase',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'max:120',
                /*
                 * `ignore()` scopes the check to the active business, so
                 * saving without changing the slug is not a self-collision.
                 *
                 * Soft-deleted rows are intentionally still counted: a deleted
                 * business keeps its slug reserved. That is the DEFAULT
                 * behaviour — `Rule::unique()` resolves the table name and
                 * asks the presence verifier for a count, bypassing Eloquent
                 * global scopes, so the SoftDeletes scope never applies.
                 * `withoutTrashed()` is the method that would exclude them.
                 *
                 * `withTrashed()` was previously chained here and does not
                 * exist on `Illuminate\Validation\Rules\Unique`, so every
                 * settings save died with a 500 instead of validating.
                 */
                Rule::unique('businesses', 'slug')
                    ->ignore($business?->getKey()),
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
        ];
    }

    /**
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
