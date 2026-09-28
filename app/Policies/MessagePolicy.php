<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Message;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for staff‑to‑tenant messages.
 *
 * A message is always attached to the active business (via the morph `business`
 * relation).  The policy enforces three independent checks:
 *
 *   1. `guardTenant()`    – 404 if the message belongs to a different tenant
 *                        than the active business.
 *   2. `guardParentTenant()` – 404 if the message's business does not match
 *                        the active business.
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check uses `canInBusiness` rather than a bare `can()` to
 * avoid the cross‑business leak described in `ReportPolicy`.
 */
class MessagePolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::MESSAGES_VIEW, $business);
    }

    public function view(User $user, Message $message): bool
    {
        $this->guardTenant($message);
        $this->guardParentTenant($message);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $message)
            && $user->canInBusiness(PermissionName::MESSAGES_VIEW, $message->business_id);
    }

    public function send(User $user, Message $message): bool
    {
        // The message is always created attached to a business, so the parent
        // authorisation is enough; we also check the business‑level permission.
        $this->guardParentTenant($message);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->canInBusiness(PermissionName::MESSAGES_SEND, $message->business_id);
    }

    public function reply(User $user, Message $message): bool
    {
        // A reply is just another message attached to the same business/tenant;
        // the same guards apply.
        return $this->send($user, $message);
    }

    public function delete(User $user, Message $message): bool
    {
        $this->guardTenant($message);
        $this->guardParentTenant($message);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $message)
            && $user->canInBusiness(PermissionName::MESSAGES_DELETE, $message->business_id);
    }

    /**
     * Prove the message's parent business belongs to the active business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Message $message): void
    {
        $business = $message->business;

        if ($business === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel(\App\Models\Business::class);
        }

        $this->guardTenant($business);
    }
}