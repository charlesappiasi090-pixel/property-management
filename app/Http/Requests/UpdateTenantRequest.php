<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a tenant.
 *
 * Extends `TenantRequest` rather than repeating it, because the only two
 * differences are real: the ability being checked, and the name-uniqueness
 * rule, which must ignore the record being edited.
 *
 * Two things genuinely differ from the create form:
 *   - the ability is `update` on the tenant, not `create`;
 *   - the name-uniqueness rule must ignore the record being edited.
 */
class UpdateTenantRequest extends TenantRequest
{
    public function authorize(): bool
    {
        $tenant = $this->tenant();

        return $tenant instanceof Tenant && ($this->user()?->can('update', $tenant) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /*
         * `ignore($tenant)` is what stops the edit form rejecting its own
         * current name as a duplicate. Without it, saving a tenant without
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
            Rule::unique('tenants', 'name')
                ->where('business_id', $this->activeBusinessId())
                ->whereNull('deleted_at')
                ->ignore($this->tenant()),
        ];

        return $rules;
    }

    protected function tenant(): ?Tenant
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }
}