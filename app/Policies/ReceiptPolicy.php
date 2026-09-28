<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Receipt;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for receipts.
 *
 * A receipt is always linked to a payment, which is always linked to a lease
 * and therefore to a tenant and a property.  The policy enforces three
 * independent checks:
 *
 *   1. `guardTenant()`    – 404 if the receipt belongs to a different tenant
 *                        than the active business (via the payment → lease).
 *   2. `guardParent()`    – 404 if the payment's parent lease's parent
 *                        property/unit belongs to a different business (the
 *                        payment's business_id must match the active business).
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check names the business rather than calling a bare
 * `$user->can(...)`. A bare `can()` resolves through Spatie's team id, so it
 * would answer "a member of THIS business holding the permission in
 * WHATEVER business was last active" – a user who is an owner of landlord A
 * and a maintenance worker in landlord B would be granted, or refused, on the
 * wrong tenant's grants. See `User::canInBusiness()`.
 *
 * Quotas are NOT checked here. "May this user add a receipt?" is a capability,
 * answered above; "may this business add ANOTHER one?" is a plan limit, and it
 * depends on current usage, so it belongs in the service that evaluates the
 * write where it is evaluated against the database inside the write's transaction.
 */
class ReceiptPolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::PAYMENTS_VIEW, $business);
    }

    public function view(User $user, Receipt $receipt): bool
    {
        $this->guardTenant($receipt);
        $this->guardParentTenant($receipt);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $receipt)
            && $user->canInBusiness(PermissionName::PAYMENTS_VIEW, $receipt->business_id);
    }

    public function create(User $user, Receipt $receipt): bool
    {
        $this->guardTenant($receipt);
        $this->guardParentTenant($receipt);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $receipt)
            && $user->canInBusiness(PermissionName::PAYMENTS_CREATE, $receipt->business_id);
    }

    public function update(User $user, Receipt $receipt): bool
    {
        $this->guardTenant($receipt);
        $this->guardParentTenant($receipt);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $receipt)
            && $user->canInBusiness(PermissionName::PAYMENTS_UPDATE, $receipt->business_id);
    }

    public function delete(User $user, Receipt $receipt): bool
    {
        $this->guardTenant($receipt);
        $this->guardParentTenant($receipt);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $receipt)
            && $user->canInBusiness(PermissionName::PAYMENTS_DELETE, $receipt->business_id);
    }

    /**
     * Prove the receipt's parent payment (and thereby its lease and property)
     * belongs to the active business.
     *
     * A receipt is always attached to a payment, which is always attached to a
     * lease, which is always attached to a property (and optionally a unit).
     * This method proves that property belongs to the active business, preventing
     * a scenario where a receipt somehow points at another landlord's payment.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Receipt $receipt): void
    {
        $payment = $receipt->payment;

        if ($payment === null) {
            // Should never happen if the route model binding works, but guard
            // against it rather than crashing.
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(Payment::class);
        }

        $this->guardTenant($payment);
    }
}