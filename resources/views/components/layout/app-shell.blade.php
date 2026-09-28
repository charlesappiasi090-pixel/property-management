@props(['title' => null])

@php
    $user = auth()->user();
    $business = app(\App\Support\Tenancy\BusinessContext::class)->business();
        $can = fn (\App\Enums\PermissionName $p): bool => $business !== null
        && ($user?->canInBusiness($p, $business) ?? false);

    /*
    |--------------------------------------------------------------------------
    | Sidebar
 navigation
    |--------------------------------------------------------------------------
    |
    | Each item names the permission that reveals it. Hiding a link is a
    | USABILITY decision only — the route is protected by the `permission`
    | middleware and the controller authorises again. A user who guesses a URL
    | still gets a 403.
    |
    | Items whose feature arrives in a later phase are listed with a
    | `Phase N` label and are NOT shown while the route does not exist, so the
    | navigation never links to a 404. They light up as each phase lands.
    |
    */
    $primaryNav = [
        ['route' => 'app.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],

        ['group' => 'Portfolio'],
        ['route' => 'app.properties.index', 'label' => 'Properties', 'icon' => 'building', 'perm' => \App\Enums\PermissionName::PROPERTIES_VIEW, 'phase' => 2],
        ['route' => 'app.tenants.index', 'label' => 'Tenants', 'icon' => 'users', 'perm' => \App\Enums\PermissionName::TENANTS_VIEW, 'phase' => 3],
        ['route' => 'app.leases.index', 'label' => 'Leases', 'icon' => 'document-text', 'perm' => \App\Enums\PermissionName::LEASES_VIEW, 'phase' => 4],

        ['group' => 'Finance'],
        ['route' => 'app.payments.index', 'label' => 'Rent & Payments', 'icon' => 'banknotes', 'perm' => \App\Enums\PermissionName::PAYMENTS_VIEW, 'phase' => 5],
        ['route' => 'app.expenses.index', 'label' => 'Expenses', 'icon' => 'receipt-percent', 'perm' => \App\Enums\PermissionName::EXPENSES_VIEW, 'phase' => 8],
        ['route' => 'app.reports.index', 'label' => 'Reports', 'icon' => 'chart-bar', 'perm' => \App\Enums\PermissionName::REPORTS_VIEW, 'phase' => 6],

        ['group' => 'Operations'],
        ['route' => 'app.maintenance.index', 'label' => 'Maintenance', 'icon' => 'wrench-screwdriver', 'perm' => \App\Enums\PermissionName::MAINTENANCE_VIEW, 'phase' => 7],
        ['route' => 'app.documents.index', 'label' => 'Documents', 'icon' => 'folder', 'perm' => \App\Enums\PermissionName::DOCUMENTS_VIEW, 'phase' => 9],
        ['route' => 'app.staff.index', 'label' => 'Staff', 'icon' => 'user-group', 'perm' => \App\Enums\PermissionName::STAFF_VIEW],
    ];

    $settingsNav = [
        ['route' => 'app.settings.business.edit', 'label' => 'Business settings', 'icon' => 'cog-6-tooth'],
        ['route' => 'app.subscription.show', 'label' => 'Billing & plan', 'icon' => 'credit-card'],
        ['route' => 'profile.edit', 'label' => 'Your profile', 'icon' => 'user-circle'],
    ];

    $userBusinesses = $user?->availableBusinesses()->get() ?? collect();
@endphp

<div class="flex min-h-full">
    {{-- ---------------------------------------------------------------- --}}
    {{-- Mobile sidebar scrim                                            --}}
    {{-- `hidden` rather than `opacity-0` so it is removed from the tab    --}}
    {{-- order entirely while closed.                                      --}}
    {{-- ---------------------------------------------------------------- --}}
    <div
        data-sidebar-overlay
        class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"
        aria-hidden="true"
    ></div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Sidebar                                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    <aside
        data-sidebar
        id="app-sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col
               bg-sidebar text-slate-300 transition-transform duration-200 ease-out
               lg:translate-x-0"
        aria-label="Main navigation"
    >
        {{-- Brand + close (mobile only) --}}
        <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5">
            <a href="{{ route('app.dashboard') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                    PH
                </span>
                <span class="text-base font-semibold text-white">
                    {{ config('propertyhub.name') }}
                </span>
            </a>

            <button
                type="button"
                data-sidebar-close
                class="-mr-1.5 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
            >
                <span class="ph-sr-only">Close navigation</span>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Business switcher --}}
        @if ($userBusinesses->isNotEmpty())
            <div class="relative shrink-0 border-b border-white/10 p-3">
                <button
                    type="button"
                    data-switcher-toggle
                    class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-white/10"
                    aria-expanded="false"
                    aria-controls="business-switcher-menu"
                >
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand-500/20 text-xs font-bold text-brand-200 uppercase">
                        {{ \Illuminate\Support\Str::substr($business?->name ?? '?', 0, 2) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-white">
                            {{ $business?->name ?? 'No business' }}
                        </span>
                        <span class="block truncate text-xs text-slate-400">
                            {{ $business?->subscription?->plan?->name ?? 'No active plan' }}
                        </span>
                    </span>
                    <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                    </svg>
                </button>

                <div
                    id="business-switcher-menu"
                    data-switcher-menu
                    class="mt-1 hidden overflow-hidden rounded-lg bg-slate-800 p-1 shadow-lg ring-1 ring-white/10"
                >
                    @foreach ($userBusinesses as $option)
                        <form method="POST" action="{{ route('business.switch') }}">
                            @csrf
                            <input type="hidden" name="business_id" value="{{ $option->id }}">

                            <button
                                type="submit"
                                class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors
                                       hover:bg-white/10 {{ $option->id === $business?->id ? 'bg-white/10 text-white' : '' }}"
                            >
                                <span class="flex size-6 shrink-0 items-center justify-center rounded bg-white/10 text-[10px] font-bold uppercase">
                                    {{ \Illuminate\Support\Str::substr($option->name, 0, 2) }}
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{ $option->name }}</span>
                                @if ($option->id === $business?->id)
                                    <svg class="size-4 shrink-0 text-brand-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @endif
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Navigation --}}
        <nav class="ph-scroll flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($primaryNav as $item)
                @if (isset($item['group']))
                    <p class="px-2.5 pt-4 pb-1 text-xs font-semibold tracking-wider text-slate-500 uppercase first:pt-0">
                        {{ $item['group'] }}
                    </p>
                    @continue
                @endif

                @php
                    /*
                     * Two independent conditions, and `&&` binds tighter than
                     * `?:`, so the original one-liner
                     *
                     *     $permitted && ($item['show'] ?? false) === true ? true : ...
                     *
                     * parsed as a nested conditional rather than the flat
                     * conjunction it looked like. That is easy to misread and
                     * easy to "fix" into a bug, so it is spelled out here.
                     *
                     * A link appears when BOTH hold:
                     *   1. the user holds the item's permission (items with no
                     *      `perm` key, like Dashboard, are always permitted);
                     *   2. the route is actually registered.
                     *
                     * Condition 2 is what keeps unbuilt phases out of the nav
                     * instead of shipping links that 404.
                     */
                    $permitted = ! isset($item['perm']) || $can($item['perm']);
                    $visible = $permitted && Route::has($item['route']);
                @endphp

                @if ($visible)
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'group flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                            'bg-sidebar-active text-white' => $active,
                            'text-slate-300 hover:bg-sidebar-hover hover:text-white' => ! $active,
                        ])
                    >
                        <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @elseif (isset($item['phase']))
                    {{-- A quiet marker so the roadmap is visible without
                         shipping links to 404s. --}}
                    <span
                        class="group flex cursor-not-allowed items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-500"
                        title="Arriving in Phase {{ $item['phase'] }}"
                    >
                        <x-icon :name="$item['icon']" class="size-5 shrink-0 opacity-50" />
                        <span class="truncate">{{ $item['label'] }}</span>
                        <span class="ml-auto rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                            P{{ $item['phase'] }}
                        </span>
                    </span>
                @endif
            @endforeach
        </nav>

        {{-- Subscription footer --}}
        @if ($business?->status?->needsAttention() ?? false)
            <a
                href="{{ route('app.subscription.show') }}"
                class="mx-3 mb-3 block rounded-lg border border-amber-400/30 bg-amber-400/10 p-3 transition-colors hover:bg-amber-400/20"
            >
                <p class="text-xs font-semibold text-amber-200">Action required</p>
                <p class="mt-0.5 text-xs text-amber-100/80">Your plan needs attention.</p>
            </a>
        @elseif ($business?->isOnTrial() && ($business?->trialDaysRemaining() ?? 0) <= 7)
            <a
                href="{{ route('app.subscription.plans') }}"
                class="mx-3 mb-3 block rounded-lg border border-sky-400/30 bg-sky-400/10 p-3 transition-colors hover:bg-sky-400/20"
            >
                <p class="text-xs font-semibold text-sky-200">
                    {{ $business->trialDaysRemaining() }} days left in trial
                </p>
                <p class="mt-0.5 text-xs text-sky-100/80">Choose a plan to keep going.</p>
            </a>
        @endif

        {{-- User --}}
        <div class="shrink-0 border-t border-white/10 p-3">
            <div class="flex items-center gap-3 px-2.5 py-2">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                    {{ $user?->initials() }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-white">{{ $user?->name }}</span>
                    <span class="block truncate text-xs text-slate-400">{{ $user?->email }}</span>
                </span>
            </div>

            <div class="mt-1 space-y-1">
                @foreach ($settingsNav as $item)
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm transition-colors',
                            'bg-sidebar-active text-white' => $active,
                            'text-slate-400 hover:bg-white/10 hover:text-white' => ! $active,
                        ])
                    >
                        <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-sm text-slate-400 transition-colors hover:bg-white/10 hover:text-white"
                    >
                        <x-icon name="arrow-right-start-on-rectangle" class="size-5 shrink-0" />
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Main column                                                     --}}
    {{-- ---------------------------------------------------------------- --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white/80 px-4 backdrop-blur sm:px-6">
            <button
                type="button"
                data-sidebar-open
                class="-ml-1 rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                aria-controls="app-sidebar"
                aria-expanded="false"
            >
                <span class="ph-sr-only">Open navigation</span>
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-semibold text-slate-900">
                    {{ $title ?? 'Dashboard' }}
                </h1>
            </div>

            @hasSection('headerActions')
                <div class="flex shrink-0 items-center gap-2">
                    @yield('headerActions')
                </div>
            @endif
        </header>

        {{-- Flash messages --}}
        <div class="px-4 pt-4 sm:px-6">
            <x-flash-messages />
        </div>

        {{-- Page content --}}
        <main id="main-content" class="flex-1 px-4 py-6 sm:px-6">
            {{ $slot }}
        </main>
    </div>
</div>

@once
    {{--
        `@push` must be closed by `@endpush`, NOT by `@endonce`.

        `@push` calls `startPush()`, which runs `ob_start()`. Only `stopPush()`
        (compiled from `@endpush`) calls `ob_get_clean()`. Wrapping the push in
        `@once` does NOT balance it, so without an `@endpush` here the buffer
        is left open and every page using this shell orphans an output buffer
        per render — which PHPUnit reports as a risky test.

        Order matters too: `@endpush` closes the push, then `@endonce` closes
        the once-guard.
    --}}
    @push('scripts')
        <script>
            /**
             * Mobile sidebar open/close.
             *
             * The state is reflected on <body data-sidebar-open> rather than
             * in a JS variable, so the CSS can drive the transform with a
             * plain attribute selector — no class toggling to keep in sync
             * with the markup. It also makes the state inspectable and
             * survivable if this script is replaced later.
             */
            (function () {
                const body = document.body;
                const openBtn = document.querySelector('[data-sidebar-open]');
                const closeBtn = document.querySelector('[data-sidebar-close]');
                const overlay = document.querySelector('[data-sidebar-overlay]');
                const sidebar = document.querySelector('[data-sidebar]');

                if (!openBtn || !sidebar) return;

                const isOpen = () => body.hasAttribute('data-sidebar-open');

                function setOpen(open) {
                    body.toggleAttribute('data-sidebar-open', open);
                    overlay?.classList.toggle('hidden', !open);
                    openBtn.setAttribute('aria-expanded', String(open));

                    if (open) {
                        // Move focus into the panel so keyboard users are not
                        // left behind on the page behind the overlay.
                        sidebar.querySelector('[data-sidebar-close]')?.focus();
                    }
                }

                openBtn.addEventListener('click', () => setOpen(true));
                closeBtn?.addEventListener('click', () => setOpen(false));
                overlay?.addEventListener('click', () => setOpen(false));

                // Escape closes, matching the native <dialog> convention.
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && isOpen()) {
                        setOpen(false);
                        openBtn.focus();
                    }
                });

                // Clicking a nav link should close the drawer, otherwise the
                // destination is hidden behind it on a phone.
                sidebar.querySelectorAll('a[href]').forEach((link) => {
                    link.addEventListener('click', () => {
                        if (isOpen()) setOpen(false);
                    });
                });

                // Business switcher dropdown.
                const switcherToggle = document.querySelector('[data-switcher-toggle]');
                const switcherMenu = document.querySelector('[data-switcher-menu]');

                if (switcherToggle && switcherMenu) {
                    switcherToggle.addEventListener('click', () => {
                        const open = switcherMenu.classList.toggle('hidden');
                        switcherToggle.setAttribute('aria-expanded', String(!open));
                    });

                    document.addEventListener('click', (e) => {
                        if (!e.target.closest('[data-switcher-toggle]') && !e.target.closest('[data-switcher-menu]')) {
                            switcherMenu.classList.add('hidden');
                            switcherToggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            })();
        </script>
    @endpush
@endonce
