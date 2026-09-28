<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
use App\Models\Business;
use App\Models\Plan;
use App\Services\Billing\SubscriptionState;
use App\Services\Tenancy\BusinessMembershipService;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 1 onboarding: "welcome, name your business, pick a plan".
 *
 * This is the ONLY user-facing place a Business is created, which is what
 * keeps the tenant boundary meaningful: every business has a known creator,
 * an owner role, and a subscription from the moment it exists.
 *
 * Note what this controller does NOT do: it does not create a property, a
 * unit or a tenant. Those arrive in Phase 2+. Shipping them one at a time is
 * deliberate — every phase stays testable on its own.
 */
class OnboardingController extends Controller
{
    public function __construct(
        protected BusinessMembershipService $membership,
        protected SubscriptionState $subscriptions,
    ) {}

    /**
     * The onboarding form.
     *
     * Someone who already owns a business has nothing to do here, so they are
     * sent onward rather than shown a form that would let them create a
     * second tenant by accident.
     */
    public function create(Request $request, BusinessContext $context): View|RedirectResponse
    {
        if ($request->user()->businesses()->exists()) {
            return redirect()->route('app.dashboard');
        }

        return view('onboarding.create', [
            'plans' => Plan::query()->publiclyVisible()->get(),
            'defaultPlan' => Plan::query()->publiclyVisible()->orderBy('sort_order')->value('code'),
        ]);
    }

    public function store(StoreBusinessRequest $request, BusinessContext $context): RedirectResponse
    {
        $plan = Plan::query()->where('code', $request->string('plan'))->firstOrFail();

        /*
         * ONE transaction around all three writes.
         *
         * Each service opens its own transaction internally, but nested
         * `DB::transaction()` calls in Laravel reuse the OUTERMEST transaction
         * via savepoints — so wrapping here makes business + owner membership +
         * subscription genuinely atomic. Without this outer transaction a
         * failure in `startTrial()` (a plan deleted between validation and
         * here, a full table, a deadlock) would leave an orphaned business whose
         * owner has no subscription: read-only, unexplained, and only
         * discoverable by querying the database directly.
         */
        $business = DB::transaction(function () use ($request, $plan): Business {
            $business = $this->membership->createBusinessWithOwner(
                owner: $request->user(),
                name: $request->string('name')->toString(),
                slug: $request->filled('slug') ? $request->string('slug')->toString() : null,
                attributes: $request->businessAttributes(),
            );

            $this->subscriptions->startTrial($business, $plan);

            return $business;
        });

        // Make the new business the active one for the rest of this request
        // and the next, so the redirect lands inside the right tenant.
        $context->set($business);
        $request->session()->put('business_id', $business->getKey());

        return redirect()
            ->route('app.dashboard')
            ->with('success', sprintf(
                'Welcome to PropertyHub. Your %s trial is now active.',
                $plan->name
            ));
    }
}
