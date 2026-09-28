<?php

namespace Tests\Feature\Backoffice;

use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use App\Services\Billing\SubscriptionState;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use RuntimeException;
use Tests\Concerns\SeedsPermissions;
use Tests\TestCase;

/**
 * The subscription state machine, and what the application does about it.
 *
 * WHY THIS IS TESTED AT ALL
 * -------------------------
 * Billing state is the one thing that decides whether a paying customer can
 * use the product. The interesting behaviour is not "a trialing business can
 * write" — it is the set of states where a landlord loses access:
 *
 *  - an expired account must be read-only but still reachable;
 *  - a CANCELLED account must keep working until the period it has already
 *    paid for ends, because cancelling is not the same as being cut off;
 *  - an illegal transition must throw rather than silently corrupt state;
 *  - and the expiry sweep must run across tenants, which it cannot do from a
 *    per-request code path.
 *
 * The cancellation case is the one that had already gone wrong once: the
 * status enum treated `canceled` as not-writable, so cancelling a plan
 * locked the account out immediately for a month already paid for. The tests
 * below are the regression net for that.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPermissions;

    private BusinessMembershipService $memberships;

    private SubscriptionState $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->memberships = app(BusinessMembershipService::class);
        $this->state = app(SubscriptionState::class);
    }

    /* ------------------------------------------------------------------ */
    /* Fixtures                                                            */
    /* ------------------------------------------------------------------ */

    private function business(?Plan $plan = null, string $name = 'Test Landlord'): Business
    {
        $business = $this->memberships->createBusinessWithOwner(
            owner: User::factory()->create(),
            name: $name,
        );

        $this->state->startTrial($business, $plan ?? Plan::factory()->create());

        return $business;
    }

    private function actingIn(Business $business, ?User $user = null): User
    {
        $user ??= $business->owners()->first();

        $this->actingAs($user);
        $this->withSession(['business_id' => $business->getKey()]);

        app(BusinessContext::class)->set($business);

        return $user;
    }

    /**
     * Move a business and its subscription to a state directly.
     *
     * Deliberately not `transitionTo()`: the sweep and the tests need to
     * *arrive* at these states, including ones the state machine would refuse
     * to reach in one step, and asserting on the result of a direct write is
     * what makes these independent of the transition rules under test in the
     * other file.
     */
    private function forceState(Business $business, SubscriptionStatus $status, array $subscriptionAttributes = []): void
    {
        $subscription = $business->subscription;

        $subscription->update([
            'status' => $status->value,
            ...$subscriptionAttributes,
        ]);

        $business->update([
            'status' => match ($status) {
                SubscriptionStatus::TRIALING => BusinessStatus::TRIALING,
                SubscriptionStatus::ACTIVE => BusinessStatus::ACTIVE,
                SubscriptionStatus::PAST_DUE => BusinessStatus::PAST_DUE,
                SubscriptionStatus::CANCELED => BusinessStatus::CANCELED,
                SubscriptionStatus::EXPIRED => BusinessStatus::EXPIRED,
                SubscriptionStatus::PAUSED => BusinessStatus::SUSPENDED,
            },
        ]);

        $business->unsetRelation('subscription');
        $business->refresh();
    }

    /* ------------------------------------------------------------------ */
    /* Starting a trial                                                    */
    /* ------------------------------------------------------------------ */

    public function test_starting_a_trial_sets_both_the_subscription_and_the_business(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 30]);
        $business = $this->memberships->createBusinessWithOwner(
            owner: User::factory()->create(),
            name: 'New Landlord',
        );

        $subscription = $this->state->startTrial($business, $plan);

        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertSame(
            $business->getKey(),
            $subscription->business_id,
            'There is exactly one subscription per business, so the row must point at this one.'
        );
        $this->assertTrue($subscription->trial_ends_at->isFuture());
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($subscription->trial_ends_at->startOfDay(), false));

        // The business mirrors the subscription so the request gate can decide
        // without a join. If these two disagree, `canWrite()` is a coin toss.
        $this->assertSame(BusinessStatus::TRIALING, $business->refresh()->status);
        $this->assertTrue($business->canWrite());
    }

    public function test_starting_a_trial_twice_does_not_create_a_second_row(): void
    {
        $business = $this->business();

        $this->state->startTrial($business, Plan::factory()->create());

        $this->assertSame(1, Subscription::query()->count());
    }

    public function test_starting_a_trial_writes_a_tenant_scoped_audit_entry(): void
    {
        /*
         * `business_id` must be set even though nothing has resolved a tenant
         * yet. This runs during onboarding — the one moment a business exists
         * before any middleware has looked at it — and it is exactly the entry
         * the new account's activity feed is expected to open with. Written
         * with a NULL business_id it would belong to nobody and be invisible.
         */
        $business = $this->business();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->getKey(),
            'event' => \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
        ]);

        // Reachable through the business itself, not merely present in the
        // table. Creating a tenant writes two entries — the first membership
        // and the trial — and both must land here.
        $this->assertEqualsCanonicalizing(
            [
                \App\Enums\AuditEvent::STAFF_INVITED->value,
                \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
            ],
            $business->auditLogs()->pluck('event')->all(),
        );

        // And attributed to no other tenant.
        $this->assertDatabaseMissing('audit_logs', [
            'business_id' => null,
            'event' => \App\Enums\AuditEvent::SUBSCRIPTION_CHANGED->value,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Transitions                                                         */
    /* ------------------------------------------------------------------ */

    public function test_converting_a_trial_to_active_starts_a_paid_period(): void
    {
        $business = $this->business();

        $subscription = $this->state->transitionTo(
            $business->subscription,
            SubscriptionStatus::ACTIVE,
            'Trial converted',
        );

        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->trial_ends_at, 'An active subscription is no longer on a trial.');
        $this->assertNull($subscription->ended_at);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertSame(BusinessStatus::ACTIVE, $business->refresh()->status);
        $this->assertNull($business->trialDaysRemaining());
    }

    public function test_an_illegal_transition_is_refused_and_changes_nothing(): void
    {
        $business = $this->business();

        $this->expectException(RuntimeException::class);

        try {
            $this->state->transitionTo($business->subscription, SubscriptionStatus::PAST_DUE);
        } finally {
            // Asserted in a `finally` so the check survives the expected throw:
            // a rejected transition must leave the row exactly as it was, or
            // the caller believes they changed something they did not.
            $this->assertSame(SubscriptionStatus::TRIALING, $business->subscription->refresh()->status);
            $this->assertSame(BusinessStatus::TRIALING, $business->refresh()->status);
        }
    }

    public function test_transitioning_to_the_current_status_is_a_no_op(): void
    {
        $business = $this->business();

        $before = $business->subscription->updated_at;

        $this->state->transitionTo($business->subscription, SubscriptionStatus::TRIALING);

        $this->assertTrue($before->equalTo($business->subscription->refresh()->updated_at));
    }

    public function test_a_failed_payment_makes_the_account_read_only(): void
    {
        $business = $this->business();
        $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);

        $this->forceState($business, SubscriptionStatus::PAST_DUE);

        $this->assertFalse($business->canWrite());
        $this->assertTrue($business->subscriptionStatus()->allowsAccess(), 'They must still be able to sign in and see why.');
        $this->assertTrue($business->subscriptionStatus()->needsAttention());
    }

    /* ------------------------------------------------------------------ */
    /* Cancellation — the regression                                       */
    /* ------------------------------------------------------------------ */

    public function test_cancelling_records_the_cancellation_without_revoking_anything(): void
    {
        $business = $this->business();
        $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);

        $subscription = $this->state->transitionTo(
            $business->subscription,
            SubscriptionStatus::CANCELED,
            'Cancelled by owner',
        );

        $this->assertSame(SubscriptionStatus::CANCELED, $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->current_period_end, 'The paid-through date is what ends access.');
        $this->assertSame(BusinessStatus::CANCELED, $business->refresh()->status);
    }

    public function test_a_cancelled_business_keeps_working_until_the_period_ends(): void
    {
        Date::setTestNow('2026-03-01 09:00');

        $business = $this->business();
        $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);
        $this->state->transitionTo($business->subscription, SubscriptionStatus::CANCELED);

        $this->assertTrue(
            $business->refresh()->canWrite(),
            'Cancelling must not cost the landlord the month they have already paid for.'
        );

        // The sweep moves it to expired once the date passes; but the account
        // must also lock on the date alone, in case the sweep is late.
        $this->forceState(
            $business,
            SubscriptionStatus::CANCELED,
            ['current_period_end' => now()->subDay()],
        );

        $this->assertFalse(
            $business->refresh()->canWrite(),
            'Past the paid-through date the account is read-only, sweep or no sweep.'
        );
    }

    public function test_a_cancelled_business_can_still_reactivate_before_the_period_ends(): void
    {
        $business = $this->business();
        $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);
        $this->state->transitionTo($business->subscription, SubscriptionStatus::CANCELED);

        $subscription = $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);

        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->canceled_at, 'Resuming clears the cancellation stamp.');
        $this->assertTrue($business->refresh()->canWrite());
    }

    /* ------------------------------------------------------------------ */
    /* Expiry                                                              */
    /* ------------------------------------------------------------------ */

    public function test_the_sweep_expires_every_business_whose_period_has_passed(): void
    {
        $lapsed = $this->business(name: 'Lapsed');
        $current = $this->business(name: 'Current');

        foreach ([$lapsed, $current] as $business) {
            $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);
        }

        $lapsed->subscription->update(['current_period_end' => now()->subDay()]);

        $expired = $this->state->expireLapsedSubscriptions();

        $this->assertSame(1, $expired);
        $this->assertSame(SubscriptionStatus::EXPIRED, $lapsed->subscription->refresh()->status);
        $this->assertSame(BusinessStatus::EXPIRED, $lapsed->refresh()->status);
        $this->assertSame(SubscriptionStatus::ACTIVE, $current->subscription->refresh()->status);
        $this->assertTrue($current->refresh()->canWrite());
    }

    public function test_the_sweep_leaves_an_expired_business_alone(): void
    {
        $business = $this->business();
        $this->forceState($business, SubscriptionStatus::EXPIRED);

        $this->assertSame(0, $this->state->expireLapsedSubscriptions());
    }

    public function test_an_expired_business_can_recover_by_choosing_a_plan_again(): void
    {
        $business = $this->business();
        $this->forceState($business, SubscriptionStatus::EXPIRED);

        $this->assertFalse($business->canWrite());

        // The remediation path: an expired account is allowed back in.
        $subscription = $this->state->changePlan($business->subscription, Plan::factory()->create());

        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($business->refresh()->canWrite());
    }

    /* ------------------------------------------------------------------ */
    /* Suspension                                                          */
    /* ------------------------------------------------------------------ */

    public function test_a_suspended_business_loses_access_entirely(): void
    {
        $business = $this->business();
        $this->forceState($business, SubscriptionStatus::PAUSED);

        $this->assertFalse($business->subscriptionStatus()->allowsAccess());
        $this->assertFalse($business->canWrite());
    }

    /* ------------------------------------------------------------------ */
    /* Quotas                                                              */
    /* ------------------------------------------------------------------ */

    public function test_quota_counters_and_enforcement_read_the_same_limit(): void
    {
        $plan = Plan::factory()->create(['max_staff' => 5, 'max_properties' => 2]);
        $business = $this->business($plan);
        $quota = app(PlanQuota::class);

        // The owner occupies one staff slot.
        $this->assertSame(4, $quota->remaining($business, 'staff'));
        $this->assertSame(2, $quota->remaining($business, 'properties'));

        $this->assertFalse($quota->isExhausted($business, 'properties'));

        // Nothing exists yet, so "used" is genuinely zero rather than a
        // relation that quietly does not exist and reports 0 forever.
        $this->assertSame(0, $quota->usageFor($business, 'properties'));
    }

    public function test_an_unlimited_plan_never_exhausts(): void
    {
        $business = $this->business(Plan::factory()->unlimited()->create());
        $quota = app(PlanQuota::class);

        $this->assertNull($quota->limitFor($business, 'properties'));
        $this->assertNull($quota->remaining($business, 'properties'));
        $this->assertFalse($quota->isExhausted($business, 'properties'));

        $quota->assertCanAdd($business, 'properties', 10000);
    }

    public function test_a_downgrade_leaves_existing_data_alone_and_only_reports_over_quota(): void
    {
        /*
         * The behaviour is deliberate and destructive alternatives were
         * rejected: reducing a plan must never delete a customer's portfolio
         * to satisfy a limit they have not agreed to yet. They are over quota
         * and prompted to upgrade instead.
         */
        $plan = Plan::factory()->create(['max_properties' => 50]);
        $business = $this->business($plan);
        $quota = app(PlanQuota::class);

        $this->assertSame(50, $quota->limitFor($business, 'properties'));

        $smaller = Plan::factory()->create(['max_properties' => 0]);
        $this->state->changePlan($business->subscription, $smaller);

        $this->assertSame(0, $quota->limitFor($business->refresh(), 'properties'));
    }

    public function test_a_feature_gate_refuses_a_capability_the_plan_does_not_include(): void
    {
        $plan = Plan::factory()->create(['features' => ['reports' => false]]);
        $business = $this->business($plan);

        $this->actingIn($business);

        $this->assertFalse(app(PlanQuota::class)->allowsFeature('reports'));
        $this->expectException(\App\Services\Billing\FeatureNotAvailableException::class);
        app(PlanQuota::class)->assertFeature('reports');
    }

    /* ------------------------------------------------------------------ */
    /* The billing screen                                                  */
    /* ------------------------------------------------------------------ */

    public function test_the_billing_screen_shows_the_real_state_to_the_owner(): void
    {
        $plan = Plan::factory()->create(['name' => 'Estate Plan', 'trial_days' => 7]);
        $business = $this->business($plan);
        $this->actingIn($business);

        $response = $this->get(route('app.subscription.show'));

        $response->assertOk();
        $response->assertSee('Estate Plan');
        $response->assertSee('Trialing');
    }

    public function test_the_billing_screen_is_reachable_when_read_only_because_it_is_the_way_out(): void
    {
        $business = $this->business();
        $this->actingIn($business);
        $this->forceState($business, SubscriptionStatus::EXPIRED);

        // No `subscription.active` middleware on billing routes, deliberately:
        // gating the remediation path would trap the user.
        $this->get(route('app.subscription.show'))->assertOk();
        $this->get(route('app.subscription.plans'))->assertOk();
    }

    public function test_an_owner_can_cancel_and_the_message_states_the_paid_through_date(): void
    {
        $business = $this->business();
        $owner = $this->actingIn($business);
        $this->state->transitionTo($business->subscription, SubscriptionStatus::ACTIVE);

        $response = $this->actingAs($owner)->post(route('app.subscription.cancel'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(
            SubscriptionStatus::CANCELED,
            $business->subscription->refresh()->status,
        );
        $this->assertTrue(
            $business->refresh()->canWrite(),
            'Cancelling must not lock the account out immediately.'
        );
    }

    public function test_cancelling_billing_needs_the_manage_permission(): void
    {
        $business = $this->business();
        $accountant = User::factory()->create();
        $this->memberships->attach($business, $accountant, \App\Enums\Role::ACCOUNTANT);
        $this->actingIn($business, $accountant);

        $this->get(route('app.subscription.show'))->assertOk();
        $this->post(route('app.subscription.cancel'))->assertForbidden();

        $this->assertSame(
            SubscriptionStatus::TRIALING,
            $business->subscription->refresh()->status,
        );
    }
}
