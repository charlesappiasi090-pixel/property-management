<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Lease;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for leases.
 *
 * A lease is always owned by a tenant and a property (or unit).  The policy
 * enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the lease belongs to a different tenant
 *                        than the active business.
 *   2. `guardParent()`    – 404 if the lease's parent property/unit belongs
 *                        to a different business (the unit's denormalised
 *                        `business_id` must match the active business).
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in
 * WHATEVER business was last active" – a user who is an owner of landlord A
 * and a maintenance worker in landlord B would be granted, or refused, on the
 * wrong tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user add a lease?" is a capability,
 * answered above; "may this business add ANOTHER one?" is a plan limit, and it
 * depends on current usage, so it belongs in the service that evaluates the
 * write where it is evaluated against the database inside the write's transaction.
 */
class LeasePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::LEASES_VIEW, $business);
    }

    public function view(User $user, Lease $lease): bool
    {
        $this->guardTenant($lease);
        $this->guardParentTenant($lease);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $lease)
            && $user->canInBusiness(PermissionName::LEASES_VIEW, $lease->business_id);
    }

    public function create(User $user, Lease $lease): bool
    {
        $this->guardTenant($lease);
        $this->guardParentTenant($lease);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $lease)
            && $user->canInBusiness(PermissionName::LEASES_CREATE, $lease->business_id);
    }

    public function update(User $user, Lease $lease): bool
    {
        $this->guardTenant($lease);
        $this->guardParentTenant($lease);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $lease)
            && $user->canInBusiness(PermissionName::LEASES_UPDATE, $lease->business_id);
    }

    public function delete(User $user, Lease $lease): bool
    {
        $this->guardTenant($lease);
        $this->guardParentTenant($lease);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $lease)
            && $user->canInBusiness(PermissionName::LEASES_DELETE, $lease->business_id);
    }

    /**
     * Prove the lease's parent property (or unit) belongs to the active
     * business too.
     *
     * A lease is always attached to a property, and optionally to a unit.
     * The unit carries a denormalised `business_id`; this method proves that
     * business matches the active context, preventing a scenario where a unit
     * somehow points at another landlord's property.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Lease $lease): void
    {
        $parent = $lease->property;

        if ($parent === null) {
            // Should never happen if the route model binding works, but guard
            // against it rather than crashing.
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(Property::class);
        }

        $this->guardTenant($parent);
    }
}