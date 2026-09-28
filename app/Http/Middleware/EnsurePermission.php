<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Support\Tenancy\BusinessContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate: `->middleware('can:properties.update')`.
 *
 * Used for coarse, route-shaped checks ("this whole controller needs
 * payments.record"). Fine-grained, record-specific decisions (may THIS user
 * update THIS lease?) belong in a Policy — a middleware cannot see the model.
 *
 * Using both is not redundant:
 *   - middleware  -> 404/403 before the controller runs, keeps unauthorised
 *                    users out of code that assumes authorisation.
 *   - policy      -> per-record correctness, including ownership.
 *
 * The permission is evaluated against the business that `ResolveActiveBusiness`
 * resolved for this request, by name rather than through spatie's ambient team
 * id, so the gate cannot be satisfied by a permission held in some other
 * workspace.
 */
class EnsurePermission
{
    public function __construct(protected BusinessContext $context) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'You must be signed in to do that.');
        }

        // Platform operators bypass tenant permissions, but every action they
        // take is recorded with is_super_admin_action = true.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $business = $this->context->businessOrFail();

        foreach ($this->normalise($permissions) as $permission) {
            /*
             * `canInBusiness()`, not `can()`.
             *
             * `can()` reaches spatie's team-scoped `roles` relation, so it is
             * only correct because `ResolveActiveBusiness` happened to set the
             * team id earlier in this request. That is an implicit ordering
             * dependency for the gate that guards every route in the
             * back office - if this middleware is ever reached from a queue
             * job, a console-invoked route, or a test that forgot to arm the
             * context, it would authorise against the wrong landlord. The
             * resolved business is already in hand, so there is no reason to
             * depend on ambient state here.
             */
            if ($user->canInBusiness($permission, $business)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(
            $this->messageFor($permissions)
        );
    }

    /**
     * Accepts the enum, a string, or several of either (any-of semantics,
     * matching Blade's @can('a|b')).
     *
     * @param  array<int, string|PermissionName>  $permissions
     * @return list<string>
     */
    protected function normalise(array $permissions): array
    {
        $flat = [];

        foreach ($permissions as $permission) {
            foreach (explode('|', $permission instanceof PermissionName ? $permission->value : $permission) as $piece) {
                $flat[] = trim($piece);
            }
        }

        return array_values(array_filter($flat));
    }

    /**
     * Naming the missing permission helps developers; whether it reaches the
     * browser is controlled by config('permission.display_permission_in_exception'),
     * which defaults to false for production.
     *
     * @param  array<int, string|PermissionName>  $permissions
     */
    protected function messageFor(array $permissions): string
    {
        $names = array_map(
            static fn ($p) => $p instanceof PermissionName ? $p->value : $p,
            $permissions
        );

        return 'You do not have permission to perform this action.'
            .' Required: '.implode(' or ', $names).'.';
    }
}
