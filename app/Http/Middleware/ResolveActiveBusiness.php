<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the ACTIVE tenant for the request and puts it in BusinessContext.
 *
 * This is the single point where "which business is this request about?" is
 * decided. Everything downstream — global scopes, spatie's team id, policies,
 * blade navigation, audit logs — reads that one answer.
 *
 * RESOLUTION ORDER (first match wins)
 * ----------------------------------
 *   1. The business id already in the session. Set ONLY by the POST-only
 *      `business.switch` route — there is no query-string override, because a
 *      tenant switch is a state change and must not be triggerable by a GET.
 *   2. `users.preferred_business_id`, validated against membership.
 *   3. The user's first active membership.
 *   4. null — the user simply has no business, and tenant-scoped routes will
 *      redirect them to onboarding.
 *
 * WHY NOT A SUBDOMAIN?
 * --------------------
 * Subdomain-per-tenant is the better long-term design, but it couples the
 * cookie/session domain to every subdomain and breaks on XAMPP/127.0.0.1 local
 * development. The session-based switch satisfies every security requirement
 * (membership is still verified on every request) and can be swapped for
 * subdomain resolution later without touching a single controller.
 */
class ResolveActiveBusiness
{
    public function __construct(protected BusinessContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            $this->context->set(null);

            return $next($request);
        }

        $business = $this->resolve($request, $user->id, $user->resolveDefaultBusiness()?->getKey());

        $this->context->set($business);

        if ($business !== null) {
            $this->syncSession($request, $business);
        }

        return $next($request);
    }

    /**
     * @param  int  $userId
     */
    protected function resolve(Request $request, int $userId, ?int $defaultId): ?Business
    {
        /*
         * SESSION ONLY — NOT `?business=`.
         *
         * An earlier version also honoured a `?business=` query parameter. That
         * is a state change reachable by a plain GET, which means:
         *   - any cross-site image/redirect/link could switch a signed-in user's
         *     active tenant out from under them;
         *   - a shared or bookmarked URL silently retargets the workspace;
         *   - it contradicted the CSRF-protected POST switch route.
         *
         * Switching is therefore POST-only, via `SwitchBusinessController`,
         * which writes `business_id` into the session below.
         */
        $requested = $request->session()->get('business_id');

        if (is_numeric($requested)) {
            $candidateId = (int) $requested;

            // Membership is re-checked on EVERY request, never cached on the
            // session. If a membership is revoked mid-session the very next
            // request loses access — that is the whole point.
            $business = $this->findMemberBusiness($userId, $candidateId);

            if ($business !== null) {
                return $business;
            }

            // The requested business is not (or no longer) theirs. Forget the
            // stale session value so it cannot cause a redirect loop.
            $request->session()->forget('business_id');
        }

        if ($defaultId !== null) {
            $business = $this->findMemberBusiness($userId, $defaultId);

            if ($business !== null) {
                return $business;
            }
        }

        // Nothing cached: fall back to the first active membership.
        return Business::query()
            ->whereHas('members', fn ($q) => $q
                ->where('users.id', $userId)
                // NOT `wherePivot('is_active', true)` — see findMemberBusiness().
                ->where('business_user.is_active', true))
            ->orderBy('businesses.name')
            ->first();
    }

    protected function findMemberBusiness(int $userId, int $businessId): ?Business
    {
        return Business::query()
            ->where('id', $businessId)
            ->whereHas('members', fn ($q) => $q
                ->where('users.id', $userId)
                /*
                 * The pivot column must be named EXPLICITLY here.
                 *
                 * `wherePivot()` only exists on the BelongsToMany RELATION. The
                 * closure passed to `whereHas()` receives an Eloquent Builder
                 * for the related model, where `wherePivot` is not a real
                 * method — so Eloquent's dynamic-where handler swallows it and
                 * produces:
                 *
                 *     where "pivot" = "is_active"
                 *
                 * A column named `pivot` compared against the STRING
                 * "is_active". That is always false, so the subquery matched
                 * nothing and every authenticated user was bounced to
                 * onboarding regardless of their membership. It failed
                 * SILENTLY: no error, no warning, just a tenant that could
                 * never be resolved.
                 *
                 * Qualifying the real pivot column keeps the predicate honest.
                 */
                ->where('business_user.is_active', true))
            ->first();
    }

    protected function syncSession(Request $request, Business $business): void
    {
        if ($request->session()->get('business_id') !== $business->getKey()) {
            $request->session()->put('business_id', $business->getKey());
        }
    }
}
