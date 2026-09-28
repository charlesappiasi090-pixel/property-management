<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for tenants.
 *
 * Three independent questions are answered separately, and all three are
 * required:
 *
 *   1. Is the user an active member of the business that owns this tenant?
 *      (`isMemberOfRecordOwner`)
 *   2. Does that business grant them the specific permission?
 *      (`canInBusiness`, with the business named explicitly)
 *   3. Is the tenant in that business at all?
 *      (`guardTenant`, which 404s rather than 403s)
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in
 * WHATEVER business was last active" – a user who is an owner of landlord A
 * and a maintenance worker in landlord B would be granted, or refused, on the
 * wrong tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user add a tenant?" is a capability,
 * answered above; "may this business add ANOTHER one?" is a plan limit, and it
 * depends on current usage, so it belongs in the service that evaluates the
 * write where it is evaluated against the database inside the write's transaction.
 */
class TenantPolicy
{
    use AuthorisesTenantRecords;

    /**
     * Reach the tenants index.
     */
    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::TENANTS_VIEW, $business);
    }

    /**
     * Read one tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        $this->guardTenant($tenant);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $tenant)
            && $user->canInBusiness(PermissionName::TENANTS_VIEW, $tenant->business_id);
    }

    /**
     * Open the create form.
     */
    public function create(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::TENANTS_CREATE, $business);
    }

    /**
     * Edit a tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        $this->guardTenant($tenant);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $tenant)
            && $user->canInBusiness(PermissionName::TENANTS_UPDATE, $tenant->business_id);
    }

    /**
     * Remove a tenant.
     *
     * A tenant with active leases may not be deleted – the service layer
     * will reject the request with a clear message.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        $this->guardTenant($tenant);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $tenant)
            && $user->canInBusiness(PermissionName::TENANTS_DELETE, $tenant->business_id);
    }
}