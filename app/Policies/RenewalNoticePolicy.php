<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\RenewalNotice;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for lease renewal notices.
 *
 * A renewal notice is always attached to the active business (via the morph
 * `business` relation) and linked to a specific lease + tenant.  The policy
 * enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the notice belongs to a different tenant
 *                        than the active business.
 *   2. `guardParentTenant()` – 404 if the notice's business does not match
 *                        the active business.
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check uses `canInBusiness` rather than a bare `can()` to
 * avoid the cross‑business leak described in `ReportPolicy`.
 */
class RenewalNoticePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::RENEWAL_NOTICES_VIEW, $business);
    }

    public function view(User $user, RenewalNotice $notice): bool
    {
        $this->guardTenant($notice);
        $this->guardParentTenant($notice);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $notice)
            && $user->canInBusiness(PermissionName::RENEWAL_NOTICES_VIEW, $notice->business_id);
    }

    public function create(User $user, RenewalNotice $notice): bool
    {
        $this->guardParentTenant($notice);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->canInBusiness(PermissionName::RENEWAL_NOTICES_CREATE, $notice->business_id);
    }

    public function update(User $user, RenewalNotice $notice): bool
    {
        $this->guardTenant($notice);
        $this->guardParentTenant($notice);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $notice)
            && $user->canInBusiness(PermissionName::RENEWAL_NOTICES_UPDATE, $notice->business_id);
    }

    public function delete(User $user, RenewalNotice $notice): bool
    {
        $this->guardTenant($notice);
        $this->guardParentTenant($notice);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $notice)
            && $user->canInBusiness(PermissionName::RENEWAL_NOTICES_DELETE, $notice->business_id);
    }

    /**
     * Prove the notice's parent business belongs to the active business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(RenewalNotice $notice): void
    {
        $business = $notice->business;

        if ($business === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(\App\Models\Business::class);
        }

        $this->guardTenant($business);
    }
}