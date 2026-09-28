<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\Role;
use App\Models\Business;
use App\Models\User;

/**
 * Authorisation for the tenant itself — the business record and the actions
 * that operate on the whole workspace.
 *
 * WHY THIS EXISTS ALONGSIDE THE `permission` MIDDLEWARE
 * ----------------------------------------------------
 * Route middleware answers "may this user reach this URL at all". It cannot
 * answer "may this user act on THIS SPECIFIC BUSINESS", because at the point
 * the middleware runs the model is not resolved. That second question is the
 * one that protects tenant boundaries, so it belongs in a policy, which
 * receives the model.
 *
 * Both are applied. The middleware is the fast, coarse gate; the policy is the
 * precise one. Neither is a substitute for the `BelongsToBusiness` global
 * scope, which hides other tenants' rows in the first place.
 *
 * NOTE ON 404 vs 403
 * ------------------
 * `view()` returns FALSE (403) for a foreign business rather than throwing a
 * 404. That is correct here because the business the user is looking at is
 * always resolved from their own context by middleware, never taken from the
 * URL. A mismatch can therefore only mean a stale context, where a clear 403
 * is more debuggable than a 404. Row-level models (properties, units) use
 * `belongsToAnotherBusiness()` and SHOULD 404 instead, so a guessed id is not
 * confirmed to exist.
 *
 * PERMISSION CHECKS NAME THE BUSINESS
 * -----------------------------------
 * Every ability below tests membership and permission as one conjunction, and
 * both halves are pinned to the same `$business`. The permission half used to
 * be a bare `$user->can(...)`, which resolves through spatie's team id — so the
 * policy read as "a member of THIS business, holding the permission in
 * WHATEVER business was last active". `canInBusiness()` closes that.
 */
class BusinessPolicy
{
    /**
     * A platform operator acting across tenants. Modelled as an explicit check
     * on the model rather than a `Gate::before`, so the exception is visible
     * at the call site and testable.
     */
    protected function isSuperAdmin(?User $user): bool
    {
        return $user?->isSuperAdmin() === true;
    }

    /**
     * List businesses. A user sees only the ones they are a member of; the
     * super admin sees all.
     */
    public function viewAny(User $user): bool
    {
        return $user->businesses()->exists() || $this->isSuperAdmin($user);
    }

    /**
     * Read a specific business.
     */
    public function view(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // `belongsToBusiness()` checks an ACTIVE membership, so a deactivated
        // or removed member loses access immediately rather than at next login.
        return $user->belongsToBusiness($business->getKey());
    }

    /**
     * Create a business for oneself.
     *
     * Deliberately permissive: signup is open. A future "businesses per user"
     * plan limit belongs in the request's `authorize()`, not here, because it
     * is a quota rather than a capability.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Edit the business profile.
     */
    public function update(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // Both conditions are required, and both matter:
        //   - membership: stops a user editing a landlord they just left;
        //   - permission: stops a maintenance worker editing company details.
        return $user->belongsToBusiness($business->getKey())
            && $user->canInBusiness(PermissionName::SETTINGS_MANAGE, $business);
    }

    /**
     * Add a member to the workspace.
     */
    public function addStaff(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->belongsToBusiness($business->getKey())
            && $user->canInBusiness(PermissionName::STAFF_INVITE, $business);
    }

    /**
     * Change or remove an existing member.
     */
    public function manageStaff(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->belongsToBusiness($business->getKey())
            && $user->canInBusiness(PermissionName::STAFF_UPDATE, $business);
    }

    /**
     * Remove a member from the workspace.
     *
     * Separate from `manageStaff()` because removing someone is a strictly
     * more dangerous action than editing them, and keeping them apart means a
     * future role can be granted one without the other.
     */
    public function removeStaff(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->belongsToBusiness($business->getKey())
            && $user->canInBusiness(PermissionName::STAFF_REMOVE, $business);
    }

    /**
     * Delete the business entirely.
     *
     * Not reachable from the UI in Phase 1 — there is no route for it. It
     * exists so the rule is stated once here rather than being reinvented
     * (probably wrongly) when the flow is built. Owner-only, and never for a
     * business with more than one owner, since deleting the account of a
     * co-owner is not a decision one person should be able to make alone.
     */
    public function delete(User $user, Business $business): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $user->belongsToBusiness($business->getKey())) {
            return false;
        }

        // Named explicitly: `hasRoleInBusiness()` without an argument consults
        // the ambient Spatie team id, so it would answer about whichever tenant
        // was active rather than the business being deleted.
        if (! $user->hasRoleInBusiness(Role::OWNER, $business)) {
            return false;
        }

        return $business->owners()->count() <= 1;
    }
}
