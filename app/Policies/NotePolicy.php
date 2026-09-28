<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Note;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for message attachments/notes.
 *
 * A note is always attached to the active business (via the morph `business`
 * relation on the parent Message) and to a specific message.  The policy
 * enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the note belongs to a different tenant
 *                        than the active business (via the parent message).
 *   2. `guardParentTenant()` – 404 if the parent message's business does not
 *                        match the active business.
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check uses `canInBusiness` rather than a bare `can()` to
 * avoid the cross‑business leak described in `ReportPolicy`.
 */
class NotePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::NOTES_VIEW, $business);
    }

    public function view(User $user, Note $note): bool
    {
        $this->guardTenant($note);
        $this->guardParentTenant($note);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $note)
            && $user->canInBusiness(PermissionName::NOTES_VIEW, $note->business_id);
    }

    public function create(User $user, Note $note): bool
    {
        $this->guardParentTenant($note);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->canInBusiness(PermissionName::NOTES_CREATE, $note->business_id);
    }

    public function update(User $user, Note $note): bool
    {
        $this->guardTenant($note);
        $this->guardParentTenant($note);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $note)
            && $user->canInBusiness(PermissionName::NOTES_UPDATE, $note->business_id);
    }

    public function delete(User $user, Note $note): bool
    {
        $this->guardTenant($note);
        $this->guardParentTenant($note);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $note)
            && $user->canInBusiness(PermissionName::NOTES_DELETE, $note->business_id);
    }

    /**
     * Prove the note's parent message belongs to the active business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Note $note): void
    {
        $business = $note->message->business;

        if ($business === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(\App\Models\Business::class);
        }

        $this->guardTenant($business);
    }
}