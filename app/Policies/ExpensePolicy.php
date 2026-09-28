<?php

namespace App\Policies;

use App\Enums\ExpenseCategory;
use App\Enums\PermissionName;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for expenses.
 *
 * An expense is always attached to a property (and optionally a lease, unit,
 * or tenant).  The policy enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the expense belongs to a different tenant
 *                        than the active business (via the property/lease/unit).
 *   2. `guardParent()`    – 404 if the expense's parent property/lease/unit
 *                        belongs to a different business (the expense's
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
 * Quotas are NOT checked here. "May this user add an expense?" is a capability,
 * answered above; "may this business add ANOTHER one?" is a plan limit, and it
 * depends on current usage, so it belongs in the service that evaluates the
 * write where it is evaluated against the database inside the write's transaction.
 */
class ExpensePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::EXPENSES_VIEW, $business);
    }

    public function view(User $user, Expense $expense): bool
    {
        $this->guardTenant($expense);
        $this->guardParentTenant($expense);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $expense)
            && $user->canInBusiness(PermissionName::EXPENSES_VIEW, $expense->business_id);
    }

    public function create(User $user, Expense $expense): bool
    {
        $this->guardTenant($expense);
        $this->guardParentTenant($expense);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $expense)
            && $user->canInBusiness(PermissionName::EXPENSES_CREATE, $expense->business_id);
    }

    public function update(User $user, Expense $expense): bool
    {
        $this->guardTenant($expense);
        $this->guardParentTenant($expense);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $expense)
            && $user->canInBusiness(PermissionName::EXPENSES_UPDATE, $expense->business_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        $this->guardTenant($expense);
        $this->guardParentTenant($expense);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $expense)
            && $user->canInBusiness(PermissionName::EXPENSES_DELETE, $expense->business_id);
    }

    /**
     * Prove the expense's parent property (or lease/unit/tenant) belongs to the
     * active business.
     *
     * An expense is always attached to a property, and optionally to a lease,
     * a unit, or a tenant.  This method proves that the top‑most parent
     * (property) belongs to the active business, preventing a scenario where an
     * expense somehow points at another landlord's property.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Expense $expense): void
    {
        $property = $expense->property;

        if ($property === null) {
            // Should never happen if the route model binding works, but guard
            // against it rather than crashing.
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(Property::class);
        }

        $this->guardTenant($property);
    }
}