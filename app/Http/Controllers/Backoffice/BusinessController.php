<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBusinessRequest;
use App\Support\Tenancy\BusinessContext;
use App\Services\Audit\AuditLogger;
use App\Enums\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Business settings — the tenant's own profile.
 *
 * Scoped to the ACTIVE business only. There is no route that takes a
 * business id from the client, which means there is no request a user could
 * craft to edit someone else's business.
 */
class BusinessController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        $business = $this->context->businessOrFail();

        // Anyone in the tenant may see its settings (the sidebar links to it
        // for everyone), but only an owner may change them. `update()` below
        // carries the stricter check.
        $this->authorize('view', $business);

        return view('backoffice.settings.business', [
            'business' => $business,
        ]);
    }

    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $business = $this->context->businessOrFail();

        // Third independent check, after the `permission:settings.manage`
        // route middleware and `UpdateBusinessRequest::authorize()`. The
        // policy is the one that also re-verifies MEMBERSHIP, which neither of
        // the other two can see.
        $this->authorize('update', $business);

        $before = $business->only(['name', 'legal_name', 'email', 'phone', 'timezone', 'currency', 'city', 'country']);

        $business->update($request->businessAttributes());

        // Log only the fields that actually moved, so the activity feed stays
        // readable instead of recording an identical before/after on every
        // save.
        $this->audit->recordChanges(
            $business,
            AuditEvent::BUSINESS_UPDATED->value,
            'Updated business settings',
            array_keys($before),
        );

        return back()->with('success', 'Business settings updated.');
    }
}
