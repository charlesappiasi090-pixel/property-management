<?php

namespace App\Http\Controllers\Backoffice;

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Billing screen (Phase 1 shell; the gateway arrives in Phase 13).
 *
 * It shows the real current state — plan, status, period, quota usage — and
 * is also the redirect target for `EnsureSubscriptionIsActive`, so a
 * read-only business always has somewhere useful to land.
 */
class SubscriptionController extends Controller
{
    public function __construct(protected BusinessContext $context) {}

    public function show(): View
    {
        $business = $this->context->businessOrFail();

        $business->loadMissing(['subscription.plan']);

        return view('backoffice.subscription.show', [
            'business' => $business,
            'subscription' => $business->subscription,
            'plan' => $business->subscription?->plan,
            'status' => $business->subscriptionStatus(),
            'trialDaysRemaining' => $business->trialDaysRemaining(),
            'canWrite' => $business->canWrite(),
            'periodDaysRemaining' => $business->subscription?->daysUntilPeriodEnd(),
        ]);
    }

    public function plans(): View
    {
        return view('backoffice.subscription.plans', [
            'plans' => Plan::query()->publiclyVisible()->get(),
            'currentPlanId' => $this->context->businessOrFail()->subscription?->plan_id,
        ]);
    }

    /**
     * Cancel the subscription.
     *
     * Nothing is revoked: the account keeps full access until
     * `current_period_end`, then goes read-only. That is the behaviour
     * `Subscription::allowsWrite()` implements, and it is why this screen says
     * "until <date>" rather than "immediately".
     *
     * Phase 13 replaces the local transition with a gateway call; the paid-
     * through date is what the gateway's own cancellation would report, so the
     * rule does not change when that arrives.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $business = $this->context->businessOrFail();
        $subscription = $business->subscription;

        if ($subscription === null) {
            return back()->with('error', 'No subscription to cancel.');
        }

        app(\App\Services\Billing\SubscriptionState::class)
            ->transitionTo($subscription, SubscriptionStatus::CANCELED, 'Cancelled by owner');

        $periodEnd = $subscription->refresh()->current_period_end;

        return back()->with('success', sprintf(
            'Your subscription is cancelled. You keep full access until %s, and the account becomes read-only after that.',
            $periodEnd?->toFormattedDateString() ?? 'the end of the current period',
        ));
    }
}
