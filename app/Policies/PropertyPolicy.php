<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Property;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for properties.
 *
 * Three independent questions are answered separately, and all three are
 * required:
 *
 *   1. Is the user an active member of the business that owns this property?
 *      (`isMemberOfRecordOwner`)
 *   2. Does that business grant them the specific permission?
 *      (`canInBusiness`, with the business named explicitly)
 *   3. Is the property in that business at all?
 *      (`guardTenant`, which 404s rather than 403s)
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in WHATEVER
 * business was last active" — a user who is an owner of landlord A and a
 * maintenance worker in landlord B would be granted, or refused, on the wrong
 * tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user add a property?" is a
 * capability, answered above; "may this business add ANOTHER one?" is a plan
 * limit, and it depends on current usage, so it belongs in
 * `App\Services\Portfolio\PropertyCatalog` where it is evaluated against the
 * database inside the write's transaction.
 */
class PropertyPolicy
{
    use AuthorisesTenantRecords;

    /**
     * Reach the properties index.
     */
    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::PROPERTIES_VIEW, $business);
    }

    /**
     * Read one property.
     */
    public function view(User $user, Property $property): bool
    {
        $this->guardTenant($property);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $property)
            && $user->canInBusiness(PermissionName::PROPERTIES_VIEW, $property->business_id);
    }

    /**
     * Open the create form.
     */
    public function create(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::PROPERTIES_CREATE, $business);
    }

    /**
     * Edit a property.
     */
    public function update(User $user, Property $property): bool
    {
        $this->guardTenant($property);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $property)
            && $user->canInBusiness(PermissionName::PROPERTIES_UPDATE, $property->business_id);
    }

    /**
     * Remove a property.
     */
    public function delete(User $user, Property $property): bool
    {
        $this->guardTenant($property);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $property)
            && $user->canInBusiness(PermissionName::PROPERTIES_DELETE, $property->business_id);
    }
}
