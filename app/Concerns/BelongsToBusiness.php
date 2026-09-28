<?php

namespace App\Concerns;

use App\Models\Business;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Makes any model a tenant-owned record.
 *
 * Every domain model from Phase 2 onwards uses this trait, which gives it:
 *
 *   1. `business()` — the owning tenant relation.
 *   2. An automatic global scope that hides rows belonging to other
 *      businesses. This is the second line of defence; the first is that
 *      controllers never build queries from unvalidated input.
 *   3. `createForBusiness()` — a factory helper that stamps `business_id`
 *      from the active context so it is impossible to insert an orphan row
 *      or a row owned by the wrong tenant.
 *
 * WHY A GLOBAL SCOPE IN ADDITION TO POLICY CHECKS
 * -----------------------------------------------
 * Policies prevent a user from *acting on* a record. A global scope prevents
 * a record from being *read at all*. Both are needed:
 *
 *   - Without the scope, one forgotten `->where('business_id', ...)` in a
 *     report or an export leaks another landlord's data. That is the single
 *     most damaging bug class in a multi-tenant SaaS, and it fails silently.
 *   - Without policies, a user could still act on a record they are not
 *     entitled to act on even if the row is visible to their tenant.
 *
 * The scope is bypassed deliberately and only in two places, both explicit
 * and both commented at the call site:
 *   - `BusinessContext::runWithoutScope()` for platform-wide admin screens.
 *   - Queued jobs that sweep ALL businesses, which call
 *     `withoutGlobalScope()` on a per-business loop instead.
 *
 * @see \App\Support\Tenancy\BusinessContext
 */
trait BelongsToBusiness
{
    /**
     * Boot the global scope. `booted()` rather than a `booted()` static so
     * subclasses can override cleanly.
     */
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $builder): void {
            $context = app(BusinessContext::class);

            /*
             * The sanctioned cross-tenant bypass.
             *
             * This check MUST come before the null check below, and it requires
             * `id() === null` as well as the flag. Without the null requirement
             * a nested `runFor($business, ...)` inside a `runWithoutScope()`
             * block would keep the bypass switched on while a tenant was
             * active — queries would silently read across every landlord.
             * Requiring both means an active business always re-arms the scope.
             *
             * The flag is only ever set by `runWithoutScope()`, which is
             * narrow, `finally`-restored, and commented at every call site.
             */
            $businessId = $context->id();

            if ($context->scopingDisabled() && $businessId === null) {
                return;
            }

            if ($businessId === null) {
                // Fail CLOSED. Outside a resolved tenant (a console command, a
                // queue worker that has not been given a business, an artisan
                // tinker session) a tenant-owned query must return nothing
                // rather than everything. Silently returning "all rows" here
                // is the classic multi-tenant data leak.
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('business_id'), $businessId);
        });

        static::creating(function (Model $model): void {
            $column = $model->qualifyColumn('business_id');

            // Stamp the active tenant when the caller did not set one.
            if (empty($model->getAttribute('business_id'))) {
                $businessId = BusinessContext::id();

                if ($businessId === null) {
                    throw new RuntimeException(
                        'Cannot persist a tenant-owned ['.class_basename($model).'] without an active business. '
                        .'Wrap the write in BusinessContext::runFor($business, ...) or pass business_id explicitly.'
                    );
                }

                $model->setAttribute('business_id', $businessId);
            }
        });
    }

    /**
     * The tenant that owns this record.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Create a record owned by the active business.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createForBusiness(array $attributes = []): static
    {
        $businessId = BusinessContext::id();

        if ($businessId === null) {
            throw new RuntimeException(
                'No active business in context. Use BusinessContext::runFor($business, fn () => ...).'
            );
        }

        return static::query()->create([
            ...$attributes,
            'business_id' => $attributes['business_id'] ?? $businessId,
        ]);
    }

    /**
     * Is this record owned by a DIFFERENT business than the active one?
     *
     * Used by policies to return 404 rather than 403 when a cross-tenant id
     * is guessed — a 403 would confirm the record exists.
     */
    public function belongsToAnotherBusiness(?Business $current = null): bool
    {
        $businessId = $current?->getKey() ?? BusinessContext::id();

        return $businessId === null || (int) $this->getAttribute('business_id') !== (int) $businessId;
    }
}
