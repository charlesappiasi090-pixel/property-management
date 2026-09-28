<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the tenant portal: `->middleware(['tenant'])`.
 *
 * The mirror image of `EnsureBackOfficeAccess`. A tenant must have an active
 * business AND the `tenant` role. Staff who guess a portal URL are sent back
 * to the dashboard.
 *
 * Row-level scoping (this tenant sees only their OWN lease) is not done here —
 * it belongs in the portal policies, because middleware cannot see the model.
 * This only answers "is this account a tenant at all?".
 */
class EnsureTenantAccess
{
    public function __construct(protected BusinessContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You must be signed in to do that.');
        }

        $business = $this->context->business();

        if ($business === null) {
            return redirect()->route('onboarding.create');
        }

        // The business is named explicitly: `hasRole()` reads the ambient
        // Spatie team id, and a portal gate that consults the wrong tenant's
        // roles would let staff into the portal and tenants out of it.
        if ($user->hasRoleInBusiness(Role::TENANT, $business)) {
            return $next($request);
        }

        return redirect()->route('app.dashboard');
    }
}
