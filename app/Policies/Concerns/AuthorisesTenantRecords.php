<?php

namespace App\Policies\Concerns;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared guard for policies on row-level tenant models.
 *
 * WHY 404 AND NOT 403
 * -------------------
 * A 403 says "this exists, and you may not have it". That is information the
 * requester did not have and cannot earn, and it turns "is landlord B's
 * property #412 in my account?" into an enumeration oracle: an attacker can
 * walk ids and watch for the difference between 403 and 404.
 *
 * `BusinessPolicy` deliberately does the opposite (it returns false for a
 * foreign business, producing a 403) because the business in that URL is
 * resolved from the requester's own context, never from the URL — a mismatch
 * there can only be a stale context, which is a bug worth showing clearly.
 *
 * A property, unit, lease or expense IS addressed by URL, so a guessed id gets
 * a 404. This trait is the single place that decision is expressed, so the next
 * row-level policy cannot quietly get it wrong.
 */
trait AuthorisesTenantRecords
{
    /**
     * Throw a 404 if the record belongs to a different tenant than the active
     * business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardTenant(Model $record): void
    {
        $business = $this->activeBusiness();

        if (method_exists($record, 'belongsToAnotherBusiness') && $record->belongsToAnotherBusiness($business)) {
            throw (new ModelNotFoundException)->setModel($record::class);
        }
    }

    /**
     * The business this request is operating in, or null if none is resolved.
     *
     * Resolved from `BusinessContext`, not from the record: by the time a
     * policy runs, `guardTenant()` has already established that the record and
     * the context agree.
     */
    protected function activeBusiness(): ?Business
    {
        return app(BusinessContext::class)->business();
    }

    /**
     * Is this user a member of the business that owns the record?
     *
     * `belongsToBusiness()` checks an ACTIVE membership, so a removed or
     * deactivated member loses access immediately instead of at next login.
     */
    protected function isMemberOfRecordOwner(User $user, Model $record): bool
    {
        $businessId = $record->getAttribute('business_id');

        return $businessId !== null && $user->belongsToBusiness((int) $businessId);
    }

    /**
     * A platform operator acting across tenants, as an explicit method so the
     * exception is visible at each call site and testable on its own.
     */
    protected function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
