<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\RentRoll;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for rent‑roll reports.
 *
 * A rent‑roll snapshot is always attached to a property (and thereby a business).
 * The policy enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the rent‑roll belongs to a different tenant
 *                        than the active business (via the property).
 *   2. `guardParent()`    – 404 if the property belongs to a different business
 *                        than the active business (the rent‑roll's business_id
 *                        must match the active business).
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in
 * WHATEVER business was last active" – a user who is an owner of landlord A
 * and a maintenance worker in landlord B would be granted, or refused, on the
 * wrong tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user view a rent‑roll report?" is a
 * capability, answered above; "may this business produce ANOTHER report?" is a
 * plan limit, and it depends on current usage, so it belongs in the service
 * that evaluates the write where it is evaluated against the database inside
 * the write's transaction.
 */
class ReportPolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::REPORTS_VIEW, $business);
    }

    public function view(User $user, RentRoll $roll): bool
    {
        $this->guardTenant($roll);
        $this->guardParentTenant($roll);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $roll)
            && $user->canInBusiness(PermissionName::REPORTS_VIEW, $roll->business_id);
    }

    public function create(User $user, RentRoll $roll): bool
    {
        $this->guardTenant($roll);
        $this->guardParentTenant($roll);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $roll)
            && $user->canInBusiness(PermissionName::REPORTS_CREATE, $roll->business_id);
    }

    public function update(User $user, RentRoll $roll): bool
    {
        $this->guardTenant($roll);
        $this->guardParentTenant($roll);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $roll)
            && $user->canInBusiness(PermissionName::REPORTS_UPDATE, $roll->business_id);
    }

    public function delete(User $user, RentRoll $roll): bool
    {
        $this->guardTenant($roll);
        $this->guardParentTenant($roll);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $roll)
            && $user->canInBusiness(PermissionName::REPORTS_DELETE, $roll->business_id);
    }

    /**
     * Prove the rent‑roll's parent property belongs to the active business.
     *
     * A rent‑roll is always attached to a property, which is always attached to
     * a business.  This method proves that property belongs to the active
     * business, preventing a scenario where a snapshot somehow points at
     * another landlord's property.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(RentRoll $roll): void
    {
        $property = $roll->property;

        if ($property === null) {
            // Should never happen if the route model binding works, but guard
            // against it rather than crashing.
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(Property::class);
        }

        $this->guardTenant($property);
    }
}