<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\AuthorisesTenantRecords;

/**
 * Authorisation for uploaded documents.
 *
 * A document is always attached to a business‑scoped model (property, tenant,
 * lease, payment, expense, maintenance request).  The policy enforces three
 * independent checks:
 *
 *   1. `guardTenant()`    – 404 if the document belongs to a different tenant
 *                        than the active business (via the parent model).
 *   2. `guardParentTenant()` – 404 if the parent model belongs to a different
 *                        business than the active business.
 *   3. Standard membership + explicit permission (`canInBusiness`).
 *
 * The permission check uses `canInBusiness` rather than a bare `can()` to
 * avoid the cross‑business leak described in `ReportPolicy`.
 */
class DocumentPolicy
{
    use AuthorisesTenantRecords;

    public function viewAny(User $user): bool
    {
        $business = $this->activeBusiness();

        return $business !== null && $user->canInBusiness(PermissionName::DOCUMENTS_VIEW, $business);
    }

    public function view(User $user, Document $document): bool
    {
        $this->guardTenant($document);
        $this->guardParentTenant($document);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $document)
            && $user->canInBusiness(PermissionName::DOCUMENTS_VIEW, $document->business_id);
    }

    public function create(User $user, Document $document): bool
    {
        // The document is always created attached to a parent, so the parent
        // authorisation is enough; we also check the business‑level permission.
        $this->guardParentTenant($document);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $user->canInBusiness(PermissionName::DOCUMENTS_CREATE, $document->business_id);
    }

    public function update(User $user, Document $document): bool
    {
        $this->guardTenant($document);
        $this->guardParentTenant($document);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $document)
            && $user->canInBusiness(PermissionName::DOCUMENTS_UPDATE, $document->business_id);
    }

    public function delete(User $user, Document $document): bool
    {
        $this->guardTenant($document);
        $this->guardParentTenant($document);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return $this->isMemberOfRecordOwner($user, $document)
            && $user->canInBusiness(PermissionName::DOCUMENTS_DELETE, $document->business_id);
    }

    /**
     * Prove the document's parent model belongs to the active business.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function guardParentTenant(Document $document): void
    {
        $parent = $document->documentable;

        if ($parent === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
                ->setModel($this->documentableModel());
        }

        $this->guardTenant($parent);
    }

    /**
     * Return the Eloquent model name that the document belongs to, for use in
     * error messages when the parent is null.
     */
    protected function documentableModel(): string
    {
        // The morph map is set up in the service provider; we default to Property
        // because most documents are property‑centric, but any model works.
        return Property::class;
    }
}