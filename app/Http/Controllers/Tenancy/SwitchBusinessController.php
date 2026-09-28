<?php

namespace App\Http\Controllers\Tenancy;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the active tenant.
 *
 * THE ONLY TENANT-SWITCHING ENDPOINT, AND IT IS POST-ONLY ON PURPOSE
 * ----------------------------------------------------------------
 * A GET switcher (`/switch?business=3`) would let a third party flip a logged-in
 * user's tenant using a crafted link or an `<img>` tag, and it would be a state
 * change performed by a safe, bookmarkable method. POST plus CSRF is the
 * correct shape for a mutation.
 *
 * Membership is re-verified here rather than trusted from the form. The
 * `Rule::exists` below checks the *user*; the membership check that actually
 * authorises the switch is `userBelongsTo()`.
 */
class SwitchBusinessController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected AuditLogger $audit,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        /*
         * Only shape is validated here — NOT existence.
         *
         * A `Rule::exists` would answer "non-existent" with a field error
         * ("The selected business is invalid") while an existing business the
         * user does not belong to falls through to the identical-wording
         * rejection below. Two different responses means the endpoint becomes
         * an oracle for which business ids are real, which is exactly what the
         * comment on `userBelongsTo()` exists to prevent.
         *
         * `find()` returning null is therefore not an error case to handle
         * separately: it takes the same path as "not yours", deliberately.
         */
        $validated = $request->validate([
            'business_id' => ['required', 'integer', 'min:1'],
        ], [], ['business_id' => 'business']);

        $user = $request->user();

        $business = Business::query()->find($validated['business_id']);

        if ($business === null || ! $this->userBelongsTo($user->getKey(), $business->getKey())) {
            // Deliberately identical wording for "does not exist" and "not
            // yours" so the endpoint cannot be used to probe which business
            // ids are real.
            return back()->with('error', 'You do not have access to that business.');
        }

        $previous = $this->context->id();

        // Persist the choice, update the tenant context, and refresh the
        // spatie team id so every permission check in THIS request already
        // reflects the new business.
        $request->session()->put('business_id', $business->getKey());
        $this->context->set($business);

        $user->forceFill(['preferred_business_id' => $business->getKey()])->save();

        $this->audit->event(
            AuditEvent::BUSINESS_SWITCHED->value,
            sprintf('Switched to %s', $business->name),
            $business,
            ['from' => $previous, 'to' => $business->getKey()],
        );

        return redirect()
            ->route('app.dashboard')
            ->with('success', sprintf('Now viewing %s.', $business->name));
    }

    protected function userBelongsTo(int $userId, int $businessId): bool
    {
        return Business::query()
            ->whereKey($businessId)
            ->whereHas('members', fn ($q) => $q
                ->where('users.id', $userId)
                /*
                 * The pivot column is named explicitly, NOT `wherePivot()`.
                 * Inside a `whereHas()` closure there is no BelongsToMany
                 * relation — just an Eloquent Builder — so `wherePivot` falls
                 * through to the dynamic-where handler and compiles to
                 * `where "pivot" = "is_active"`, which never matches. It fails
                 * open here: every switch is rejected, even legitimate ones.
                 */
                ->where('business_user.is_active', true))
            ->exists();
    }
}
