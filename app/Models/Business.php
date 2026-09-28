<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant — i.e. one landlord, agency or property-management company.
 *
 * This is the aggregate root of the entire application. A `User` is a person
 * who may work for several businesses; a `Business` owns properties, units,
 * tenants, leases, payments, expenses, maintenance, documents, staff and
 * reports. Nothing in the domain hangs off `users` directly.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property BusinessStatus $status
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
        'locale',
        'currency',
        'logo_path',
        'status',
        'trial_ends_at',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Every person with access to this business.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['job_title', 'is_active', 'is_default', 'joined_at', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * The owning account holder(s) — derived from the `owner` role rather
     * than a `owner_id` column, so that ownership and authorisation can
     * never drift apart.
     *
     * WHY THIS IS NOT `$this->members()->whereHas('roles', ...)`
     * -----------------------------------------------------------
     * Spatie's `User::roles()` relation appends
     * `wherePivot('business_id', getPermissionsTeamId())` whenever teams are
     * enabled. So a `whereHas('roles')` filter silently inherits whatever team
     * happens to be active at the moment the query runs.
     *
     * In practice that meant `owners()` returned 0 rows on any code path where
     * the ambient team was unset — `ProfileController::destroy()` is reached
     * from the guest-capable `auth` route group, which is exactly such a path.
     * The account-deletion guard then read "no owners" and allowed a sole owner
     * to delete themselves, stranding the tenant. The guard failed open because
     * of a tenant-scope leak, which is the worst possible failure direction.
     *
     * This version pins the business explicitly in the subquery and touches no
     * ambient state, so it returns the same answer regardless of which team is
     * active — including outside a request, in console commands, and in tests.
     *
     * THE CORRELATION TARGET IS `business_user.business_id`, NOT `businesses.id`
     * ----------------------------------------------------------------------
     * This relation is `members()`, which compiles to
     *
     *     select ... from users
     *       inner join business_user on users.id = business_user.user_id
     *      where business_user.business_id = ?
     *
     * The `businesses` table is not in that query at all — the business is
     * already pinned by the pivot. Correlating the subquery on `businesses.id`
     * therefore referenced a table that is not in scope and every call raised
     * `no such column: businesses.id`. `business_user.business_id` is a real
     * outer column, so it resolves in the direct query AND inside
     * `withCount()` / `whereHas()`, where `$this->getKey()` is still null.
     */
    public function owners(): BelongsToMany
    {
        $ownerRoleName = Role::OWNER->value;

        $rolesTable = config('permission.table_names.roles');
        $modelRolePivot = config('permission.table_names.model_has_roles');
        $membershipPivot = config('propertyhub.tables.business_user', 'business_user');

        return $this->members()
            ->whereExists(function ($query) use ($ownerRoleName, $rolesTable, $modelRolePivot, $membershipPivot): void {
                $query->selectRaw('1')
                    ->from($modelRolePivot)
                    ->join($rolesTable, $rolesTable.'.id', '=', $modelRolePivot.'.role_id')
                    ->whereColumn($modelRolePivot.'.model_id', 'users.id')
                    // Correlate on the pivot that is actually in scope, rather
                    // than on `$this->getKey()`: a relation method is not only
                    // called on a persisted model. `withCount()` and `whereHas()`
                    // evaluate it against a fresh instance whose key is still
                    // null, so a `$this->getKey()` pin compiles to
                    // `business_id = null` and silently matches nothing.
                    ->whereColumn($modelRolePivot.'.business_id', $membershipPivot.'.business_id')
                    // Both the pivot team and the role's own team must match.
                    // Pinning only the role NAME would let an `owner` role from
                    // a different tenant satisfy the condition.
                    ->whereColumn($rolesTable.'.business_id', $membershipPivot.'.business_id')
                    ->where($rolesTable.'.name', $ownerRoleName);
            });
    }

    /**
     * The single subscription row. `HasOne`, not `HasMany`: the
     * `subscriptions_business_unique` constraint guarantees at most one.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Managed properties.
     *
     * PlanQuota reaches this relation by name to answer "how many properties
     * has this business used?", so the method name is a contract — renaming it
     * silently turns every property limit into "unlimited" for a plan that has
     * one, because `usageFor()` falls back to 0 when the relation is missing.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Units across every property, for the same quota reason.
     *
     * Goes through `properties`? No — `units.business_id` is denormalised
     * precisely so this is one indexed count rather than a join.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * The subscription status, falling back to a sensible default when the
     * business has no subscription row yet (a manually provisioned tenant).
     */
    public function subscriptionStatus(): SubscriptionStatus
    {
        return $this->subscription?->status
            ?? ($this->status === BusinessStatus::ACTIVE
                ? SubscriptionStatus::ACTIVE
                : SubscriptionStatus::EXPIRED);
    }

    /**
     * Can this business currently write data?
     */
    public function canWrite(): bool
    {
        if (config('propertyhub.grace_on_subscription') && app()->environment('local', 'testing')) {
            return true;
        }

        /*
         * Both statuses must permit writes, so a business left mirroring
         * `active` while its subscription says `expired` cannot slip through.
         */
        $mirroredWritable = $this->status->allowsWrite() && $this->subscriptionStatus()->allowsWrite();

        if ($mirroredWritable) {
            return true;
        }

        /*
         * The one status the mirror cannot answer for itself: `canceled`.
         *
         * `BusinessStatus::CANCELED` is not in `allowsWrite()`, and it must not
         * be - "cancelled" alone does not say whether the month is paid for.
         * The subscription row does, via `current_period_end`, so the decision
         * is delegated rather than duplicated here.
         */
        return $this->status === BusinessStatus::CANCELED
            && $this->subscription !== null
            && $this->subscription->allowsWrite();
    }

    /**
     * Has the trial run out? Drives the "X days left" banner.
     */
    public function trialDaysRemaining(): ?int
    {
        if ($this->status !== BusinessStatus::TRIALING || $this->trial_ends_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false);
    }

    public function isOnTrial(): bool
    {
        return $this->status === BusinessStatus::TRIALING;
    }

    /**
     * Money is rendered with the business' own currency symbol, falling back
     * to the global default so a fresh signup never renders "USD 1,200.00".
     */
    public function currencySymbol(): string
    {
        return strtoupper($this->currency) === strtoupper(config('propertyhub.currency'))
            ? (string) config('propertyhub.currency_symbol')
            : $this->currency;
    }

    /**
     * The name to print on receipts and reports.
     */
    public function displayName(): string
    {
        return $this->legal_name ?: $this->name;
    }

    /* ------------------------------------------------------------------ */
    /* Query scopes                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope('business');
    }

    /**
     * Businesses whose trial is about to expire — used by the daily sweep.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeTrialEndingWithin(Builder $query, int $days): Builder
    {
        return $query->where('status', BusinessStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)]);
    }
}
