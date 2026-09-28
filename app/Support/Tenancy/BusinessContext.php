<?php

namespace App\Support\Tenancy;

use App\Exceptions\NoActiveBusinessException;
use App\Models\Business;
use Closure;

/**
 * Per-request holder for the ACTIVE tenant.
 *
 * Registered as a container singleton, so for the lifetime of one request
 * (or one queued job) the "current business" is resolved exactly once and
 * every tenant-scoped query, permission check and policy decision agrees on
 * it. Passing the business down as a method argument everywhere would be both
 * noisy and easy to forget — and forgetting is what causes data leaks.
 *
 * IT ALSO SETS SPATIE'S TEAM ID
 * -----------------------------
 * spatie/laravel-permission resolves roles per "team". We map team =>
 * business, so every `hasRole()` / `can()` call inside the request is already
 * scoped to the active business. That is what makes "the same role name,
 * different meaning per landlord" work without any extra checks in the
 * policies.
 *
 * LIFECYCLE
 * ---------
 *   - Resolved by `ResolveActiveBusiness` middleware from the route, the
 *     session, or the user's default membership.
 *   - Cleared at the end of every request by `ForgetActiveBusiness` so that
 *     a long-lived worker (queue, Octane) can never carry one user's tenant
 *     into the next job.
 *   - `runFor()` / `runWithoutScope()` allow a controlled, explicit scope for
 *     console commands and platform-admin work.
 */
class BusinessContext
{
    protected ?Business $business = null;

    /**
     * Set when the context was established with a scope explicitly disabled.
     * Prevents a global scope added *during* a `runWithoutScope()` block from
     * being accidentally re-applied mid-block.
     */
    protected bool $scopingDisabled = false;

    /**
     * Resolved business, if any.
     */
    public function business(): ?Business
    {
        return $this->business;
    }

    /**
     * The active business id, or null when no tenant is resolved.
     */
    public function id(): ?int
    {
        return $this->business?->getKey();
    }

    public function has(): bool
    {
        return $this->business !== null;
    }

    /**
     * Establish the active tenant.
     *
     * Intended for the `ResolveActiveBusiness` middleware and for tests.
     */
    public function set(?Business $business): void
    {
        $this->business = $business;

        // Keep spatie in lockstep so role/permission lookups are tenant-scoped.
        setPermissionsTeamId($business?->getKey());

        $this->forgetTeamScopedRelations();
    }

    /**
     * Clear the tenant. MUST be called at the end of the request lifecycle.
     */
    public function forget(): void
    {
        $this->business = null;
        $this->scopingDisabled = false;

        setPermissionsTeamId(null);

        $this->forgetTeamScopedRelations();
    }

    /**
     * Drop relations that were resolved for the PREVIOUS team.
     *
     * Spatie's `User::roles()` is not just a query — it is a relation, and
     * `loadMissing('roles')` caches the result on the model instance. The
     * relation itself is team-scoped, so a collection loaded while business A
     * was active keeps returning A's roles after the team id moves to B.
     *
     * Nothing re-reads it, because from Spatie's point of view the relation is
     * already loaded. So every `can()` and `hasPermissionTo()` call after a
     * team change silently answers about the OLD landlord. That is a
     * cross-tenant authorisation leak, and it is reachable in production:
     *
     *   - `SwitchBusinessController` changes the tenant mid-request, after
     *     earlier middleware may already have resolved roles.
     *   - A queue worker or Octane keeps the same model instance across jobs.
     *     `ForgetActiveBusiness` cleared the team but not the collection, so
     *     job two inherited job one's roles — the exact carry-over the
     *     middleware exists to prevent.
     *
     * Invalidating here, in the one place that changes teams, is what makes
     * "set a tenant" mean the tenant actually applies to subsequent checks.
     *
     * Guarded because this also runs in contexts where there is no signed-in
     * user at all — a console command, a queued job, a migration. Resolving the
     * guard there is harmless (it simply reports no user), but the check keeps
     * that intent explicit rather than incidental.
     */
    protected function forgetTeamScopedRelations(): void
    {
        if (! app()->bound('auth')) {
            return;
        }

        $user = app('auth')->user();

        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * The active business, or a thrown exception.
     *
     * Use this in controllers where a tenant is genuinely mandatory. The
     * exception renders as a 403 rather than a 500 because
     * `NoActiveBusinessException` is registered in the exception handler.
     *
     * @throws NoActiveBusinessException
     */
    public function businessOrFail(): Business
    {
        if ($this->business === null) {
            throw new NoActiveBusinessException;
        }

        return $this->business;
    }

    /**
     * Run a callback with the given business active, restoring the previous
     * context afterwards. Used by console commands and tests.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Business $business, Closure $callback): mixed
    {
        $previous = $this->business;

        $this->set($business);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    /**
     * Run a callback with NO tenant active and the global scope removed.
     *
     * DANGER: this is the only sanctioned way to see across tenants. It is
     * reserved for
     *   - platform-level console commands (`php artisan propertyhub:sweep-*`)
     *   - cross-tenant super-admin screens
     *   - the billing reconciliation job
     * Every call site must say in a comment why cross-tenant reads are safe.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runWithoutScope(Closure $callback): mixed
    {
        $previous = $this->business;
        $previousDisabled = $this->scopingDisabled;

        $this->business = null;
        $this->scopingDisabled = true;
        setPermissionsTeamId(null);
        $this->forgetTeamScopedRelations();

        try {
            return $callback();
        } finally {
            $this->business = $previous;
            $this->scopingDisabled = $previousDisabled;
            setPermissionsTeamId($previous?->getKey());
            $this->forgetTeamScopedRelations();
        }
    }

    /**
     * Are cross-tenant reads currently permitted?
     */
    public function scopingDisabled(): bool
    {
        return $this->scopingDisabled;
    }
}
