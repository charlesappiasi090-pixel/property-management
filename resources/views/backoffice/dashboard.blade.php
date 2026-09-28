@extends('layouts.app')

@section('title', 'Dashboard')

@section('headerActions')
    <x-status-badge :status="$business->status" class="hidden sm:inline-flex" />

    @if ($canInviteStaff && $canWrite)
        <x-button :href="route('app.staff.create')" size="sm">
            <x-icon name="plus" class="size-4" />
            <span class="hidden sm:inline">Invite staff</span>
            <span class="sm:hidden">Invite</span>
        </x-button>
    @endif
@endsection

@section('content')
    {{-- ------------------------------------------------------------------ --}}
    {{-- Greeting                                                           --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Welcome back, {{ \Illuminate\Support\Str::before($user->name, ' ') }}
        </h2>
        <p class="mt-1 text-sm text-slate-600">
            Here is where things stand with <span class="font-medium text-slate-800">{{ $business->name }}</span>.
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Account banners                                                    --}}
    {{-- A read-only account must say so at the top, in plain words, before
         the user starts filling in a form that will be rejected. --}}
    @unless ($canWrite)
        <div class="mb-6 ph-alert ph-alert-warning" role="alert">
            <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">This account is read-only</p>
                <p class="mt-0.5 text-sm">
                    You can browse and export everything, but new entries are disabled until billing is resolved.
                </p>
            </div>
            <x-button :href="route('app.subscription.show')" size="sm" class="shrink-0">
                Fix billing
            </x-button>
        </div>
    @endunless

    @if ($business->isOnTrial() && $trialDaysRemaining !== null && $trialDaysRemaining > 0)
        <div class="mb-6 ph-alert ph-alert-info">
            <x-icon name="information-circle" class="size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">
                    {{ $trialDaysRemaining }} {{ \Illuminate\Support\Str::plural('day', $trialDaysRemaining) }} left in your trial
                </p>
                <p class="mt-0.5 text-sm">
                    {{ $plan?->name ?? 'Your plan' }} &middot; Trial ends {{ $business->trial_ends_at?->format('j M Y') }}.
                </p>
            </div>
            <x-button :href="route('app.subscription.plans')" size="sm" class="shrink-0">
                Choose a plan
            </x-button>
        </div>
    @endif

    @if ($business->status->needsAttention())
        <div class="mb-6 ph-alert ph-alert-danger" role="alert">
            <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">
                    @if ($business->status === \App\Enums\BusinessStatus::PAST_DUE)
                        We could not take your last payment
                    @else
                        Your subscription has expired
                    @endif
                </p>
                <p class="mt-0.5 text-sm">Update your payment details to restore full access.</p>
            </div>
            <x-button :href="route('app.subscription.show')" size="sm" class="shrink-0">
                Update payment
            </x-button>
        </div>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Quick actions                                                      --}}
    {{-- Only links whose ROUTE EXISTS are rendered. The "coming next" items
         from the sidebar are honest about their phase, but the primary action
         row stays limited to what actually works. --}}
    @php
        $quickActions = array_values(array_filter([
            ['route' => 'app.staff.create', 'label' => 'Invite a team member', 'icon' => 'user-group', 'perm' => \App\Enums\PermissionName::STAFF_INVITE],
            ['route' => 'app.settings.business.edit', 'label' => 'Business settings', 'icon' => 'cog-6-tooth', 'perm' => \App\Enums\PermissionName::SETTINGS_MANAGE],
            ['route' => 'app.subscription.plans', 'label' => 'Compare plans', 'icon' => 'credit-card', 'perm' => null],
            ['route' => 'profile.edit', 'label' => 'Update your profile', 'icon' => 'user-circle', 'perm' => null],
        ], fn ($action) => Route::has($action['route'])
            && ($action['perm'] === null || $user->canInBusiness($action['perm'], $business))
            && ($action['route'] !== 'app.staff.create' || $canWrite)));
    @endphp

    @if (count($quickActions) > 0)
        <section class="mb-6">
            <h3 class="ph-sr-only">Quick actions</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ route($action['route']) }}"
                        class="ph-card flex items-center gap-3 p-4 transition-colors hover:border-brand-300 hover:bg-brand-50/40"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <x-icon :name="$action['icon']" class="size-5" />
                        </span>
                        <span class="text-sm font-medium text-slate-700">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------------ --}}
    {{-- Plan usage                                                         --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="ph-card lg:col-span-2">
            <div class="ph-card-header">
                <h3 class="ph-card-title">Plan usage</h3>
                @if ($plan)
                    <x-button :href="route('app.subscription.plans')" variant="ghost" size="sm">
                        {{ $plan->name }}
                        <x-icon name="chevron-right" class="size-4" />
                    </x-button>
                @endif
            </div>

            <div class="ph-card-body">
                @if ($plan === null)
                    <x-empty-state
                        icon="credit-card"
                        title="No plan yet"
                        message="Your account has no active plan, so there are no limits to show."
                    >
                        <x-button :href="route('app.subscription.plans')" size="sm">Choose a plan</x-button>
                    </x-empty-state>
                @else
                    {{--
                        Counters read the SAME service the write-path enforces, so
                        a limit shown here is exactly the limit that will reject
                        a request. A bar at 100% is the earliest warning the user
                        gets that the next create will fail.
                    --}}
                    <ul class="space-y-4">
                        @foreach ($quotas as $quota)
                            @php
                                $atLimit = $quota['limit'] !== null && $quota['used'] >= $quota['limit'];
                                $nearLimit = ! $atLimit && $quota['percent'] !== null && $quota['percent'] >= 80;
                            @endphp

                            <li>
                                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                    <span class="text-sm font-medium text-slate-700">{{ $quota['label'] }}</span>
                                    <span class="text-sm tabular-nums text-slate-500">
                                        {{ number_format($quota['used']) }}
                                        <span class="text-slate-400">/</span>
                                        {{ $quota['limit'] === null ? 'Unlimited' : number_format($quota['limit']) }}

                                        @if ($atLimit)
                                            <span class="ml-1 font-medium text-rose-600">at limit</span>
                                        @endif
                                    </span>
                                </div>

                                {{--
                                    Progress is decorative; the numbers above are
                                    the accessible content. `role="progressbar"`
                                    with the right aria values lets assistive tech
                                    read "3 of 5" without relying on the bar.
                                --}}
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                                    role="progressbar"
                                    aria-valuemin="0"
                                    aria-valuemax="{{ $quota['limit'] ?? 0 }}"
                                    aria-valuenow="{{ $quota['used'] }}"
                                    aria-label="{{ $quota['label'] }} usage"
                                >
                                    @if ($quota['percent'] !== null)
                                        <div
                                            @class([
                                                'h-full rounded-full transition-[width] duration-500',
                                                'bg-rose-500' => $atLimit,
                                                'bg-amber-500' => $nearLimit,
                                                'bg-brand-500' => ! $atLimit && ! $nearLimit,
                                            ])
                                            style="width: {{ $quota['percent'] }}%"
                                        ></div>
                                    @else
                                        <div class="h-full w-full rounded-full bg-slate-300"></div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Subscription summary                                             --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="ph-card">
            <div class="ph-card-header">
                <h3 class="ph-card-title">Subscription</h3>
            </div>

            <div class="ph-card-body space-y-4">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Plan</dt>
                        <dd class="font-medium text-slate-900">{{ $plan?->name ?? '—' }}</dd>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            <x-status-badge :status="$subscription?->status ?? $business->subscriptionStatus()" />
                        </dd>
                    </div>

                    @if ($business->isOnTrial() && $business->trial_ends_at)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Trial ends</dt>
                            <dd class="font-medium text-slate-900">{{ $business->trial_ends_at->format('j M Y') }}</dd>
                        </div>
                    @elseif ($subscription?->current_period_end)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">
                                {{ $business->status === \App\Enums\BusinessStatus::PAST_DUE ? 'Access until' : 'Renews' }}
                            </dt>
                            <dd class="font-medium text-slate-900">
                                {{ $subscription->current_period_end->format('j M Y') }}
                            </dd>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Team members</dt>
                        <dd class="font-medium text-slate-900 tabular-nums">{{ $staffCount }}</dd>
                    </div>
                </dl>

                <x-button :href="route('app.subscription.show')" variant="secondary" class="w-full">
                    Manage billing
                </x-button>
            </div>
        </section>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Recent activity                                                   --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card mt-6">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Recent activity</h3>
            @canIn(\App\Enums\PermissionName::AUDIT_VIEW)
                {{-- Phase 6 renders the full, filterable log. Until then the
                     dashboard shows the most recent slice and nothing links
                     to a screen that does not exist. --}}
            @endcanIn
        </div>

        @if ($recentActivity->isEmpty())
            <x-empty-state
                icon="document"
                title="Nothing here yet"
                message="Actions you take across your workspace will be listed here."
            />
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">
                        The ten most recent audit events for {{ $business->name }}.
                    </caption>

                    <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col">Summary</th>
                            <th scope="col">By</th>
                            <th scope="col" class="text-right">When</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($recentActivity as $entry)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">
                                        {{ $entry->event }}
                                    </code>
                                </td>
                                <td class="text-slate-600">{{ $entry->summary() ?: '—' }}</td>
                                <td class="whitespace-nowrap text-slate-600">
                                    {{ $entry->user?->name ?? 'System' }}
                                </td>
                                <td class="whitespace-nowrap text-right text-slate-500">
                                    <time datetime="{{ $entry->created_at?->toIso8601String() }}">
                                        {{ $entry->created_at?->diffForHumans() }}
                                    </time>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
