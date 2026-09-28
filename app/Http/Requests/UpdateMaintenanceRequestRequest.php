<?php

namespace App\Http\Requests;

use App\Models\MaintenanceRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a maintenance request.
 *
 * Extends `MaintenanceRequestRequest` rather than repeating it, because the only
 * two differences are real: the ability is `update` on the request, and the
 * status/category rules must ignore the request being edited.
 *
 * Two things genuinely differ from the create form:
 *   - the ability is `update` on the request, not `create`;
 *   - the category/priority rules must ignore the request being edited, or
 *     saving a request without touching its category would fail every time.
 */
class UpdateMaintenanceRequestRequest extends MaintenanceRequestRequest
{
    public function authorize(): bool
    {
        $request = $this->maintenanceRequest();

        return $maintenanceRequest instanceof MaintenanceRequest && ($this->user()?->can('update', $maintenanceRequest) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /*
         * `ignore($maintenanceRequest)` is what stops the edit form rejecting
         * its own current category/priority. Without it, saving a request without
         * touching its category would fail validation every single time.
         *
         * `ignore()` also supplies the `property_id` scope, because the default
         * is the primary key column; the parent rule's `whereHas` still applies
         * and keeps the comparison inside one property.
         */
        $rules['category'] = [
            'required',
            Rule::enum(MaintenanceCategory::class)
                ->where('property_id', $this->property()?->getKey())
                ->whereNull('deleted_at')
                ->ignore($this->maintenanceRequest()),
        ];

        $rules['priority'] = [
            'required',
            Rule::enum(MaintenancePriority::class),
        ];

        return $rules;
    }

    protected function maintenanceRequest(): ?MaintenanceRequest
    {
        $request = $this->route('maintenanceRequest');

        return $maintenanceRequest instanceof MaintenanceRequest ? $maintenanceRequest : null;
    }
}