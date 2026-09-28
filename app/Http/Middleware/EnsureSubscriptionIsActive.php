<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects writes when the business' subscription does not permit them.
 *
 * BEHAVIOUR
 * ---------
 * Read requests (GET/HEAD) always pass. Write requests are allowed only when
 * the active business is in a state that permits writing (trialing or
 * active), unless `propertyhub.grace_on_subscription` is enabled for local
 * development.
 *
 * WHY READ-ONLY INSTEAD OF A HARD LOGOUT
 * -------------------------------------
 * A landlord whose card failed still needs to reach their data to download
 * records, forward details to an accountant and understand the problem. A
 * hard lock is hostile and generates support calls; a read-only state with a
 * persistent banner is the industry norm and is what `BusinessStatus::allowsWrite()`
 * encodes.
 *
 * Note the check is on the *business* status AND the *subscription* status.
 * Both must permit writes, so a business left in `active` while its
 * subscription is `expired` cannot slip through.
 */
class EnsureSubscriptionIsActive
{
    /**
     * Methods that mutate state. Everything else is treated as a read.
     *
     * @var list<string>
     */
    protected const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(protected BusinessContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::WRITE_METHODS, strict: true)) {
            return $next($request);
        }

        $business = $this->context->business();

        if ($business === null) {
            // No tenant: let the downstream tenancy middleware produce the
            // accurate error rather than a misleading billing one.
            return $next($request);
        }

        $user = $request->user();

        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        if ($business->canWrite()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(422, 'Your subscription does not currently allow changes. Please update your billing details.');
        }

        // Redirect humans to the billing screen with a flash message, so the
        // UI can show a persistent banner explaining the read-only state.
        //
        // The route name is `app.subscription.show` (it lives inside the `app`
        // name prefix). Referring to a bare `subscription.show` would raise a
        // RouteNotFoundException — turning a billing problem into a 500.
        return redirect()
            ->route('app.subscription.show')
            ->with('error', 'Your plan does not currently allow changes. Data is read-only until your subscription is active.');
    }
}
