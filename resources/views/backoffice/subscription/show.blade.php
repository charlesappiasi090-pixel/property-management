@extends('layouts.app')

@section('title', 'Billing')

@php
    // Explicit about the business; see settings/business.blade.php.
    $canManage = $business !== null
        && (auth()->user()?->canInBusiness(\App\Enums\PermissionName::SUBSCRIPTION_MANAGE, $business) ?? false);
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Billing &amp; subscription</h2>
            <p class="mt-1 text-sm text-slate-600">Your plan, your billing period, and what each plan allows.</p>
        </div>

        <x-button :href="route('app.subscription.plans')" variant="secondary" size="sm">
            Compare plans
        </x-button>
    </div>

    <x-flash-messages />

    {{-- ------------------------------------------------------------------ --}}
    {{-- Attention banner                                                    --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($business->status->needsAttention())
        <div class="mb-6 ph-alert ph-alert-danger" role="alert">
            <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">
                    @if ($business->status === \App\Enums\BusinessStatus::PAST_DUE)
                        Payment failed
                    @else
                        Subscription expired
                    @endif
                </p>
                <p class="mt-0.5 text-sm">
                    Your account is read-only. Update your payment details to restore full access.
                </p>
            </div>

            @if ($canManage)
                <x-button :href="route('app.subscription.plans')" size="sm" class="shrink-0">
                    Choose a plan
                </x-button>
            @endif
        </div>
    @elseif ($business->isOnTrial() && $trialDaysRemaining !== null)
        <div class="mb-6 ph-alert ph-alert-info">
            <x-icon name="information-circle" class="size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">
                    {{ $trialDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $trialDaysRemaining) }} left in your trial
                </p>
                <p class="mt-0.5 text-sm">
                    Trial ends {{ $business->trial_ends_at?->format('j F Y') }}. No card has been charged.
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ---------------------------------------------------------------- --}}
        {{-- Current plan                                                      --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="ph-card lg:col-span-2">
            <div class="ph-card-header">
                <h3 class="ph-card-title">Current plan</h3>
                <x-status-badge :status="$status" />
            </div>

            @if ($subscription === null)
                <x-empty-state
                    icon="credit-card"
                    title="No subscription"
                    message="This business has no subscription yet."
                >
                    <x-button :href="route('app.subscription.plans')" size="sm">Choose a plan</x-button>
                </x-empty-state>
            @else
                <div class="ph-card-body">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-2xl font-bold text-slate-900">
                                {{ $plan?->name ?? 'Unknown plan' }}
                            </p>

                            @if ($plan?->tagline)
                                <p class="mt-1 text-sm text-slate-600">{{ $plan->tagline }}</p>
                            @endif

                            @if ($plan)
                                <p class="mt-3 text-sm text-slate-500">
                                    {{ $plan->formattedPrice() }} per {{ $plan->intervalLabel() }}
                                    @if ($plan->trial_days > 0 && $business->isOnTrial())
                                        <span class="text-slate-400">
                                            &middot; {{ $plan->trial_days }}-day trial
                                        </span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if ($canManage && $status !== \App\Enums\SubscriptionStatus::CANCELED)
                            <x-button :href="route('app.subscription.plans')" variant="secondary" size="sm">
                                Change plan
                            </x-button>
                        @endif
                    </div>

                    {{-- Period ---------------------------------------------------------- --}}
                    <dl class="mt-6 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                {{ $business->status === \App\Enums\BusinessStatus::PAST_DUE ? 'Access until' : 'Current period ends' }}
                            </dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                @if ($subscription->current_period_end)
                                    {{ $subscription->current_period_end->format('j M Y') }}
                                    @if ($periodDaysRemaining !== null && $periodDaysRemaining >= 0)
                                        <span class="font-normal text-slate-500">
                                            ({{ $periodDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $periodDaysRemaining) }})
                                        </span>
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Trial ends</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                {{ $subscription->trial_ends_at?->format('j M Y') ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Account status</dt>
                            <dd class="mt-1 text-sm font-medium">
                                <x-status-badge :status="$business->status" />
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </section>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Manage                                                            --}}
        {{-- ---------------------------------------------------------------- --}}
        <div class="space-y-6">
            <section class="ph-card">
                <div class="ph-card-header">
                    <h3 class="ph-card-title">What you can do</h3>
                </div>

                <div class="ph-card-body space-y-4">
                    <div class="flex items-start gap-3">
                        @if ($canWrite)
                            <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                            <p class="text-sm text-slate-700">
                                Your account can add and edit data. All modules are available.
                            </p>
                        @else
                            <x-icon name="x-circle" class="mt-0.5 size-5 shrink-0 text-rose-500" />
                            <p class="text-sm text-slate-700">
                                Your account is <strong class="font-semibold">read-only</strong>. You can browse and export
                                everything, but new entries are rejected until billing is resolved.
                            </p>
                        @endif
                    </div>

                    <x-button :href="route('app.dashboard')" variant="secondary" class="w-full">
                        Back to dashboard
                    </x-button>
                </div>
            </section>

            {{-- ---------------------------------------------------------------- --}}
            {{-- Cancel                                                           --}}
            {{-- Cancellation keeps access until the period end, so the copy says
                 exactly that. A "you will lose everything" warning would be a
                 lie and would push people to not cancel at all.          --}}
            {{-- ---------------------------------------------------------------- --}}
            @if ($canManage && $subscription !== null && $status !== \App\Enums\SubscriptionStatus::CANCELED)
                <section class="ph-card">
                    <div class="ph-card-header">
                        <h3 class="ph-card-title">Cancel subscription</h3>
                    </div>

                    <div class="ph-card-body space-y-4">
                        <p class="text-sm text-slate-600">
                            @if ($subscription->current_period_end)
                                You'll keep full access until
                                <strong class="font-semibold text-slate-900">
                                    {{ $subscription->current_period_end->format('j F Y') }}
                                </strong>,
                                after which the account becomes read-only. Nothing is deleted.
                            @else
                                The account will become read-only. Nothing is deleted.
                            @endif
                        </p>

                        {{--
                            No surrounding <form>. The trigger is type="button"
                            and the confirm dialog submits its own CSRF-protected
                            form to `data-confirm-action`. A form wrapper here
                            would look like it did something while never
                            submitting.
                        --}}
                        <x-button
                            type="button"
                            variant="secondary"
                            class="w-full text-rose-600"
                            data-confirm="cancel-subscription"
                            data-confirm-action="{{ route('app.subscription.cancel') }}"
                        >
                            Cancel my subscription
                        </x-button>
                    </div>
                </section>

                {{--
                    The dialog is a sibling of the <section>, not a child.

                    A <dialog> is a top-level element, so it is kept out of the
                    card's markup to keep the DOM valid rather than relying on
                    the browser to hoist it somewhere sensible.

                    `variant="secondary"` because cancelling is not a
                    destructive, irreversible action — access is retained until
                    the period ends, and nothing is deleted. The danger styling
                    is reserved for actions that cannot be undone.
                --}}
                <x-confirm-dialog
                    id="cancel-subscription"
                    title="Cancel your subscription?"
                    :message="$subscription->current_period_end
                        ? 'You keep full access until '.$subscription->current_period_end->format('j F Y').', then the account becomes read-only. No data is deleted.'
                        : 'The account will become read-only. No data is deleted.'"
                    confirm-text="Yes, cancel"
                    cancel-text="Keep my plan"
                    method="POST"
                    variant="secondary"
                    :action="route('app.subscription.cancel')"
                />
            @endif
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Payment provider note                                              --}}
    {{-- Stated plainly rather than faked: there is no gateway in Phase 1.  --}}
    {{-- ------------------------------------------------------------------ --}}
    <p class="mt-6 text-xs text-slate-400">
        Card payments are not enabled yet — no payment method is stored and nothing can be charged.
        Plan changes and cancellations take effect immediately in this environment.
    </p>
@endsection
