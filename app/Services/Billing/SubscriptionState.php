<?php

namespace App\Services\Billing;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place a subscription's status is allowed to change.
 *
 * EVERY PATH GOES THROUGH HERE
 * ----------------------------
 * Status changes arrive from at least four sources — a gateway webhook, an
 * admin action, a CLI reconciliation sweep, and a plan change in the UI — and
 * they must all behave identically. If each wrote to the column itself, the
 * `businesses.status` mirror would drift from `subscriptions.status` and the
 * request gate would start denying paying customers.
 *
 * So this service owns three things:
 *   1. validating the transition against SubscriptionStatus::allowedTransitions()
 *   2. keeping `businesses.status` in sync
 *   3. writing an audit entry
 *
 * Phase 13 adds a gateway listener that calls `applyGatewayEvent()`; it will
 * not touch the columns either.
 */
class SubscriptionState
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * Put a brand-new business on a plan's trial. Called once, at signup.
     */
    public function startTrial(Business $business, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($business, $plan) {
            $trialEndsAt = now()->addDays($plan->trial_days);

            $subscription = Subscription::query()->updateOrCreate(
                ['business_id' => $business->getKey()],
                [
                    'plan_id' => $plan->getKey(),
                    'status' => SubscriptionStatus::TRIALING,
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => now(),
                    'current_period_end' => $trialEndsAt,
                    'provider' => null,
                    'provider_customer_id' => null,
                    'provider_subscription_id' => null,
                    'provider_plan_id' => null,
                    'canceled_at' => null,
                    'ended_at' => null,
                ]
            );

            $business->update([
                'status' => BusinessStatus::TRIALING,
                'trial_ends_at' => $trialEndsAt,
            ]);

            $this->audit->event(
                \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
                sprintf('Started a %s trial ending %s', $plan->name, $trialEndsAt->toFormattedDateString()),
                $business,
                ['plan' => $plan->code, 'status' => SubscriptionStatus::TRIALING->value],
            );

            return $subscription;
        });
    }

    /**
     * Move to a new status, validating the transition.
     */
    public function transitionTo(Subscription $subscription, SubscriptionStatus $target, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $target, $reason) {
            $current = $subscription->status;

            if ($current === $target) {
                return $subscription;
            }

            if (! $current->canTransitionTo($target)) {
                throw new RuntimeException(
                    sprintf('A %s subscription cannot move to %s.', $current->value, $target->value)
                );
            }

            $changes = ['status' => $target];

            switch ($target) {
                case SubscriptionStatus::ACTIVE:
                    $changes['trial_ends_at'] = null;
                    // `$changes` is built fresh above and only carries
                    // `status`, so this always runs. Written as `=` rather than
                    // the `??=` that was here, which implied a key that cannot
                    // be present and hid that activation always starts a new
                    // paid period.
                    $changes['current_period_start'] = now();
                    $changes['ended_at'] = null;
                    $changes['canceled_at'] = null;
                    break;

                case SubscriptionStatus::CANCELED:
                    // Access continues until current_period_end, so the date
                    // is recorded but nothing is revoked.
                    $changes['canceled_at'] = now();
                    break;

                case SubscriptionStatus::EXPIRED:
                    $changes['ended_at'] = now();
                    break;

                case SubscriptionStatus::TRIALING:
                    $changes['trial_ends_at'] = now()->addDays($subscription->plan?->trial_days ?? 14);
                    $changes['ended_at'] = null;
                    break;

                default:
                    break;
            }

            $subscription->update($changes);
            $subscription->refresh();

            $this->syncBusiness($subscription->business, $target);

            $this->audit->event(
                $target === SubscriptionStatus::EXPIRED
                    ? \App\Enums\AuditEvent::SUBSCRIPTION_EXPIRED->value
                    : \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
                sprintf(
                    'Subscription moved from %s to %s%s',
                    $current->label(),
                    $target->label(),
                    $reason ? " ({$reason})" : ''
                ),
                $subscription->business,
                ['from' => $current->value, 'to' => $target->value, 'reason' => $reason],
            );

            return $subscription;
        });
    }

    /**
     * Change plan in place.
     *
     * Deliberately MUTATES the existing row instead of ending one and creating
     * another: `subscriptions.business_id` is UNIQUE, and more importantly a
     * business must never have two conflicting sets of entitlements, even for
     * a moment.
     *
     * Downgrades do not retroactively delete the properties or staff that
     * already exceed the new plan's limits. They are flagged as over-quota
     * (see `PlanQuota::isExhausted()`) so the UI can prompt an upgrade rather
     * than destroy a customer's portfolio. The billing screen in Phase 13
     * warns before confirming a downgrade.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, ?Carbon $renewAt = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $renewAt) {
            $previousPlan = $subscription->plan;

            $subscription->update([
                'plan_id' => $newPlan->getKey(),
                'status' => $subscription->status === SubscriptionStatus::EXPIRED
                    ? SubscriptionStatus::ACTIVE
                    : $subscription->status,
                'current_period_start' => now(),
                'current_period_end' => $renewAt ?? now()->addMonth(),
            ]);

            $subscription->refresh();
            $this->syncBusiness($subscription->business, $subscription->status);

            $this->audit->event(
                \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
                sprintf('Plan changed from %s to %s', $previousPlan?->name ?? 'none', $newPlan->name),
                $subscription->business,
                ['from' => $previousPlan?->code, 'to' => $newPlan->code],
            );

            return $subscription;
        });
    }

    /**
     * Expire subscriptions whose paid-through date has passed.
     *
     * This is the reconciliation sweep's job and must NOT run per-request: it
     * has to scan every business, which means deliberately crossing the
     * tenant boundary. It is therefore a queued/console operation, and the
     * Business queries inside it are wrapped in `withoutGlobalScope`.
     *
     * @return int Number of subscriptions expired.
     */
    public function expireLapsedSubscriptions(): int
    {
        $expired = 0;

        // Cross-tenant by design: this is a platform-wide sweep, which is why
        // it is a queued job and not something reachable from a controller.
        $candidates = Subscription::query()
            ->withoutGlobalScope('business')
            ->with('business')
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::CANCELED->value,
                SubscriptionStatus::PAST_DUE->value,
            ])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get();

        foreach ($candidates as $subscription) {
            if ($subscription->business === null) {
                continue;
            }

            DB::transaction(function () use ($subscription, &$expired) {
                $this->syncBusiness($subscription->business, SubscriptionStatus::EXPIRED);

                $subscription->update([
                    'status' => SubscriptionStatus::EXPIRED,
                    'ended_at' => now(),
                ]);

                $this->audit->event(
                    \App\Enums\AuditEvent::SUBSCRIPTION_EXPIRED->value,
                    'Subscription period ended without renewal; business is now read-only',
                    $subscription->business,
                );

                $expired++;
            });
        }

        return $expired;
    }

    /**
     * Mirror the subscription status onto the business, so the request gate
     * can make a decision without a join.
     */
    protected function syncBusiness(?Business $business, SubscriptionStatus $status): void
    {
        if ($business === null) {
            return;
        }

        $business->update(['status' => match ($status) {
            SubscriptionStatus::TRIALING => BusinessStatus::TRIALING,
            SubscriptionStatus::ACTIVE => BusinessStatus::ACTIVE,
            SubscriptionStatus::PAST_DUE => BusinessStatus::PAST_DUE,
            SubscriptionStatus::CANCELED => BusinessStatus::CANCELED,
            SubscriptionStatus::EXPIRED => BusinessStatus::EXPIRED,
            SubscriptionStatus::PAUSED => BusinessStatus::SUSPENDED,
        }]);
    }
}
