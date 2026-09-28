<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\JournalEntry;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for journal entries.
 *
 * A journal entry is always attached to the active business (via the morph
 * `business` relation).  The policy enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the entry belongs to a different tenant
 *                        than the active business.
 *   2. `guardParentTenant()` – 404 if the entry's business does not match
 *                        the active business.
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check uses `canInBusiness` rather than a bare `can()` to
 * avoid the cross‑business leak described in `ReportPolicy`.
 */
class JournalPolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::JOURNALS_VIEW, $business);
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        $this->guardTenant($entry);
        $this->guardParentTenant($entry);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $entry)
            && $user->canInBusiness(PermissionName::JOURNALS_VIEW, $entry->business_id);
    }

    public function create(User $user, JournalEntry $entry): bool
    {
        $this->guardParentTenant($entry);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->canInBusiness(PermissionName::JOURNALS_CREATE, $entry->business_id);
    }

    public function update(User $user, JournalEntry $entry): bool
    {
        $this->guardTenant($entry);
        $this->guardParentTenant($entry);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $entry)
            && $user->canInBusiness(PermissionName::JOURNALS_UPDATE, $entry->business_id);
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        $this->guardTenant($entry);
        $this->guardParentTenant($entry);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $entry)
            && $user->canInBusiness(PermissionName::JOURNALS_DELETE, $entry->business_id);
    }

    /**
     * Prove the entry's parent business belongs to the active business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(JournalEntry $entry): void
    {
        $business = $entry->business;

        if ($business === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(\App\Models\Business::class);
        }

        $this->guardTenant($business);
    }
}