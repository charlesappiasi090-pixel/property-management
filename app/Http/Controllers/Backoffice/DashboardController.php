<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\Billing\PlanQuota;
use App\Support\Tenancy\BusinessContext;
use Illuminate\View\View;

/**
 * The back-office dashboard shell.
 *
 * PHASE 1 IS DELIBERATELY EMPTY OF PROPERTY DATA
 * -----------------------------------------------
 * Properties, units and leases do not exist until Phase 2-4, so this
 * controller does not query them — it would be writing a dashboard whose
 * every tile is broken or permanently zero. What it DOES render is the
 * complete shell: tenant identity, subscription state, quota usage, the audit
 * trail and the staff roster. Those are real, working features that Phase 6
 * will build its metrics on top of.
 *
 * Each figure comes from a service rather than an inline query, so Phase 6
 * replaces the service bodies and the view does not change.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
        protected PlanQuota $quota,
    ) {}

    public function index(): View
    {
        $business = $this->context->businessOrFail();
        $user = request()->user();

        // Load the relations the layout needs in one round trip. Without this
        // the sidebar would issue a separate query for the business on every
        // single page render.
        $business->loadMissing(['subscription.plan', 'members']);

        return view('backoffice.dashboard', [
            'business' => $business,
            'user' => $user,

            // Subscription / billing state, surfaced as a banner.
            'subscription' => $business->subscription,
            'plan' => $business->subscription?->plan,
            'trialDaysRemaining' => $business->trialDaysRemaining(),
            'canWrite' => $business->canWrite(),

            /*
             * Decided here, not in the view.
             *
             * The template used to ask `$user->can('staff.invite')`, which is
             * a team-scoped read of mutable global state - correct only because
             * `ResolveActiveBusiness` set the team id earlier in this request.
             * The business is in hand here, so the question is asked about
             * THIS landlord. The view then just renders the answer, which is
             * also the only way a Blade template can be reasoned about.
             */
            'canInviteStaff' => $user?->canInBusiness(
                \App\Enums\PermissionName::STAFF_INVITE,
                $business
            ) ?? false,

            // Live quota counters. The dashboard and the enforcement path both
            // read PlanQuota, so "3 of 5 properties" can never disagree with
            // what the limit actually is.
            'quotas' => [
                'properties' => $this->quotaRow($business, 'properties'),
                'units' => $this->quotaRow($business, 'units'),
                'staff' => $this->quotaRow($business, 'staff'),
                'documents' => $this->quotaRow($business, 'documents'),
            ],

            // Real activity, not placeholder cards.
            'recentActivity' => $business->auditLogs()
                ->with('user')
                ->latest('created_at')
                ->limit(10)
                ->get(),

            'staffCount' => $business->members()->count(),
        ]);
    }

    /**
     * One row of the plan-usage table: used / limit / remaining.
     *
     * @return array{resource: string, label: string, used: int, limit: int|null, remaining: int|null, percent: int|null}
     */
    protected function quotaRow(\App\Models\Business $business, string $resource): array
    {
        $used = $this->quota->usageFor($business, $resource);
        $limit = $this->quota->limitFor($business, $resource);

        return [
            'resource' => $resource,
            'label' => \Illuminate\Support\Str::of($resource)->plural()->title()->toString(),
            'used' => $used,
            'limit' => $limit,
            'remaining' => $this->quota->remaining($business, $resource),
            'percent' => ($limit === null || $limit === 0)
                ? null
                : (int) min(100, round(($used / $limit) * 100)),
        ];
    }
}
