<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the back-office dashboard: `->middleware(['business'])`.
 *
 * PASSES WHEN
 *   - an active business is resolved, AND
 *   - the user is not a tenant-only account, AND
 *   - the business is not suspended.
 *
 * A tenant-only account that guesses `/app/dashboard` is redirected to their
 * portal rather than shown an error — the portal is the product for them, and
 * a 403 would be a dead end. A user with NO business at all is sent to
 * onboarding, because that is the only thing they can actually do.
 *
 * A suspended business gets a real 403: that state is a support action, and
 * the user needs to be told to contact their administrator rather than be
 * quietly redirected somewhere that will also fail.
 */
class EnsureBackOfficeAccess
{
    public function __construct(protected BusinessContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You must be signed in to do that.');
        }

        $business = $this->context->business();

        // No tenant yet -> the only productive action is creating one.
        if ($business === null) {
            return redirect()->route('onboarding.create');
        }

        if ($business->status->value === BusinessStatus::SUSPENDED->value) {
            throw new AuthorizationException(
                'This business has been suspended. Please contact support.'
            );
        }

        // Tenant-only accounts belong in the portal (Phase 14).
        //
        // They go to `portal.home`, NOT to `home`. The `home` route sends any
        // signed-in user who belongs to a business to the back-office
        // dashboard, whose `business` gate then redirects back to `home` — an
        // infinite loop. `portal.home` is behind the `tenant` gate, which a
        // tenant passes and this redirect cannot bounce them out of.
        //
        // The predicate is "holds no back-office role", NOT "holds the tenant
        // role". Testing for `tenant` looks equivalent and is not: a user who
        // is a tenant AND, say, a property manager in the same workspace holds
        // both rows, and the `tenant` test would bounce them into the portal and
        // out of a back office they are entitled to. Asking `isBackOfficeUser()`
        // for the named business answers the question the gate actually means,
        // and keeps this middleware and the `/` router agreeing on one rule.
        //
        // The business is passed EXPLICITLY. Role lookups that omit it consult
        // the ambient team id and would answer about whichever business was set
        // last rather than the one this request resolved.
        if (! $user->isSuperAdmin() && ! $user->isBackOfficeUser($business)) {
            return redirect()->route('portal.home');
        }

        return $next($request);
    }
}
