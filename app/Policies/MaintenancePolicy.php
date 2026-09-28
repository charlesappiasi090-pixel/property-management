<?php

namespace App\Policies;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Enums\PermissionName;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for maintenance requests.
 *
 * A maintenance request is always attached to a property (and optionally a
 * lease, unit, or tenant).  The policy enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the request belongs to a different tenant
 *                        than the active business (via the property/lease/unit).
 *   2. `guardParent()`    – 404 if the request's parent property/lease/unit
 *                        belongs to a different business (the request's
 *                        business_id must match the active business).
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in
 * WHATEVER business was last active" – a user who is an owner of landlord A
 * and a maintenance worker in landlord B would be granted, or refused, on the
 * wrong tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user add a maintenance request?" is
 * a capability, answered above; "may this business add ANOTHER one?" is a plan
 * limit, and it depends on current usage, so it belongs in the service that
 * evaluates the write where it is evaluated against the database inside the
 * write's transaction.
 */
class MaintenancePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::MAINTENANCE_VIEW, $business);
    }

    public function view(User $user, MaintenanceRequest $request): bool
    {
        $this->guardTenant($request);
        $this->guardParentTenant($request);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $request)
            && $user->canInBusiness(PermissionName::MAINTENANCE_VIEW, $request->business_id);
    }

    public function create(User $user, MaintenanceRequest $request): bool
    {
        $this->guardTenant($request);
        $this->guardParentTenant($request);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $request)
            && $user->canInBusiness(PermissionName::MAINTENANCE_CREATE, $request->business_id);
    }

    public function update(User $user, MaintenanceRequest $request): bool
    {
        $this->guardTenant($request);
        $this->guardParentTenant($request);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $request)
            && $user->canInBusiness(PermissionName::MAINTENANCE_UPDATE, $request->business_id);
    }

    public function delete(User $user, MaintenanceRequest $request): bool
    {
        $this->guardTenant($request);
        $this->guardParentTenant($request);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $request)
            && $user->canInBusiness(PermissionName::MAINTENANCE_DELETE, $request->business_id);
    }

    /**
     * Prove the request's parent property (or lease/unit/tenant) belongs to the
     * active business.
     *
     * A maintenance request is always attached to a property, and optionally to
     * a lease, a unit, or a tenant.  This method proves that the top‑most parent
     * (property) belongs to the active business, preventing a scenario where a
     * request somehow points at another landlord's property.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(MaintenanceRequest $request): void
    {
        $property = $request->property;

        if ($property === null) {
            // Should never happen if the route model binding works, but guard
            // against it rather than crashing.
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(Property::class);
        }

        $this->guardTenant($property);
    }
}