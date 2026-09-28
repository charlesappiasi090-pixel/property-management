<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate: `->middleware('role:owner')`.
 *
 * Used where a whole area of the product belongs to one job function and the
 * individual abilities are not worth enumerating — e.g. the billing screen
 * belongs to owners and accountants, so `role:owner|accountant`.
 *
 * The check is tenant-scoped automatically because spatie's team id is set by
 * ResolveActiveBusiness before this runs, and the business is additionally
 * passed to `hasRoleInBusiness()` so the assertion is tied to the tenant being
 * viewed rather than to whatever team happened to be set last.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You must be signed in to do that.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $accepted = [];

        foreach ($roles as $role) {
            foreach (explode('|', $role) as $piece) {
                $accepted[] = Role::tryFrom(trim($piece))?->value ?? trim($piece);
            }
        }

        /*
         * The business is named explicitly rather than left to the ambient
         * Spatie team id.
         *
         * `hasRole()` is team-scoped through global mutable state, so it answers
         * "which business is active right now" as a side effect. That is fine
         * when middleware guarantees the ordering and the context is right, but
         * it means a role gate can be satisfied by a role held in a DIFFERENT
         * landlord's workspace. Naming the tenant makes the check say what it
         * means: "this user holds this role in the business being viewed".
         */
        $business = app(BusinessContext::class)->business();

        if ($business === null) {
            return redirect()->route('onboarding.create');
        }

        foreach ($accepted as $roleName) {
            if ($user->hasRoleInBusiness($roleName, $business)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(
            'This area is restricted. Required role: '.implode(' or ', $accepted).'.'
        );
    }
}
