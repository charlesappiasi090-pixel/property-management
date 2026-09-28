<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business' current subscription to a Plan.
 *
 * The `business_id` column is UNIQUE, so there is never a question of which
 * row is authoritative. Plan CHANGES mutate this row in place (see
 * `App\Services\Billing\SubscriptionState::changePlan()`), which keeps the
 * invariant that one business has one set of entitlements.
 *
 * Provider columns are NULL until a gateway is configured in Phase 13. The
 * app never calls a gateway on a normal request: entitlements are always read
 * from here, so an outage at the provider cannot lock a paying customer out.
 */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
        'provider_plan_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ended_at',
        'quantity',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'quantity' => 'integer',
            'meta' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Effective entitlements.
     *
     * A subscription with no plan is treated as the most restrictive
     * possible state (zero quotas, no features) rather than the most
     * permissive. A dangling NULL plan_id must never be an access bypass.
     */
    public function entitlements(): array
    {
        $plan = $this->plan;

        if ($plan === null) {
            return [
                'max_properties' => 0,
                'max_units' => 0,
                'max_staff' => 0,
                'max_documents' => 0,
                'features' => [],
            ];
        }

        return [
            'max_properties' => $plan->max_properties,
            'max_units' => $plan->max_units,
            'max_staff' => $plan->max_staff,
            'max_documents' => $plan->max_documents,
            'features' => $plan->features ?? [],
        ];
    }

    public function allowsWrite(): bool
    {
        /*
         * A cancelled subscription keeps working until it has been paid
         * through.
         *
         * `SubscriptionState::transitionTo()` deliberately records
         * `canceled_at` and revokes nothing, and the state machine in
         * `SubscriptionStatus` documents `active -> canceled` as "access
         * continues to period end". A status-only check contradicts that and
         * made a landlord's account read-only the instant they cancelled, for
         * up to a month they had already paid for.
         *
         * The DATE is what ends it, not the word "canceled" - which also means
         * the account locks correctly even on a server where the expiry sweep
         * has not run yet.
         */
        return $this->status->allowsWrite()
            || ($this->status === SubscriptionStatus::CANCELED && ! $this->periodHasEnded());
    }

    /**
     * Has the paid-through date passed? Used by the reconciliation command to
     * find subscriptions that need expiring.
     */
    public function periodHasEnded(): bool
    {
        return $this->current_period_end !== null && $this->current_period_end->isPast();
    }

    public function daysUntilPeriodEnd(): ?int
    {
        return $this->current_period_end?->diffInDays(now(), false);
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIALING;
    }

    public function trialDaysRemaining(): ?int
    {
        if ($this->trial_ends_at === null || ! $this->isOnTrial()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->trial_ends_at->startOfDay(), false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::ACTIVE->value,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeNeedingAttention(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubscriptionStatus::PAST_DUE->value,
            SubscriptionStatus::CANCELED->value,
            SubscriptionStatus::EXPIRED->value,
        ]);
    }
}
