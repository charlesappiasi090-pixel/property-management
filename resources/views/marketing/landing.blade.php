@extends('layouts.marketing')

@section('title', config('propertyhub.name'))
@section('description', 'Run every property, every team and every tenant from one workspace. Role-based access, lease and rent tracking, maintenance and secure document storage for landlords and property managers.')

@section('nav')
    <a href="#workspace" class="text-sm font-medium text-slate-600 hover:text-slate-900">Workspaces</a>
    <a href="#roles" class="text-sm font-medium text-slate-600 hover:text-slate-900">Roles</a>
    <a href="#security" class="text-sm font-medium text-slate-600 hover:text-slate-900">Security</a>
    <a href="#plans" class="text-sm font-medium text-slate-600 hover:text-slate-900">Plans</a>
@endsection

@section('content')

    {{-- ---------------------------------------------------------------- --}}
    {{-- Hero                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="relative overflow-hidden border-b border-slate-200 bg-gradient-to-b from-brand-50/60 to-white">
        <div class="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-white px-3 py-1 text-xs font-semibold text-brand-700">
                        <span class="size-1.5 rounded-full bg-brand-500" aria-hidden="true"></span>
                        Property, tenant and rent management
                    </span>

                    <h1 class="mt-5 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                        Every property, every team, every tenant &mdash; in one workspace.
                    </h1>

                    <p class="mt-5 max-w-xl text-lg text-slate-600">
                        {{ config('propertyhub.name') }} gives landlords and property managers a single
                        place to track units and leases, collect rent, chase maintenance and keep
                        staff access under control &mdash; with a tenant portal on the other side of
                        the same door.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @if (Route::has('register'))
                            <x-button :href="route('register')" size="lg">Create your workspace</x-button>
                        @endif

                        <x-button :href="route('login')" variant="secondary" size="lg">Log in</x-button>
                    </div>

                    <p class="mt-4 text-sm text-slate-500">
                        Start on a free {{ $plans->first()?->trial_days ?? 14 }}-day trial. No card required.
                    </p>
                </div>

                {{-- A static illustration of the real product surface. Every
                     number and label below is a real thing the app tracks, not
                     decoration. --}}
                <div class="ph-card overflow-hidden p-0 shadow-lg">
                    <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <span class="size-2.5 rounded-full bg-slate-300" aria-hidden="true"></span>
                        <span class="size-2.5 rounded-full bg-slate-300" aria-hidden="true"></span>
                        <span class="size-2.5 rounded-full bg-slate-300" aria-hidden="true"></span>
                        <span class="ml-2 text-xs font-medium text-slate-500">
                            Portfolio overview
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-4">
                        @foreach ([
                            ['Occupancy', '92%', 'text-emerald-600'],
                            ['Rent collected', '$18,400', 'text-slate-900'],
                            ['Open maintenance', '6', 'text-amber-600'],
                            ['Leases expiring', '3', 'text-rose-600'],
                        ] as [$label, $value, $tone])
                            <div class="bg-white px-4 py-4">
                                <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                                <dd class="mt-1 text-xl font-semibold {{ $tone }}">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="border-t border-slate-200 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Needs attention
                        </p>
                        <ul class="mt-3 space-y-2.5">
                            @foreach ([
                                ['Unit 2B', 'Lease ends in 34 days', 'amber'],
                                ['Unit 4A', 'Rent overdue by 6 days', 'rose'],
                                ['Unit 1C', 'Boiler service requested', 'slate'],
                            ] as [$unit, $note, $tone])
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium text-slate-900">{{ $unit }}</span>
                                    <span class="flex items-center gap-2 text-slate-600">
                                        <span @class([
                                            'size-1.5 rounded-full',
                                            'bg-amber-500' => $tone === 'amber',
                                            'bg-rose-500' => $tone === 'rose',
                                            'bg-slate-400' => $tone === 'slate',
                                        ]) aria-hidden="true"></span>
                                        {{ $note }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- The daily work                                                    --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="border-b border-slate-200 bg-white py-16 sm:py-20">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                    The six things a landlord actually has to keep track of
                </h2>
                <p class="mt-4 text-slate-600">
                    Not a generic CRM. The recurring obligations that decide whether a
                    portfolio is healthy or quietly losing money.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    [
                        'Properties & units',
                        'Every building, unit and address in one register, with the details that actually differ between them.',
                    ],
                    [
                        'Leases & renewals',
                        'Start dates, end dates and rent review dates in one calendar, flagged '.config('propertyhub.lease_expiry_warning_days').' days out so renewals are never a surprise.',
                    ],
                    [
                        'Rent & arrears',
                        'Rent is charged on the '.config('propertyhub.rent_due_day_of_month').'st by default and reclassified as overdue after a '.config('propertyhub.rent_grace_days').'-day grace period.',
                    ],
                    [
                        'Maintenance',
                        'Requests arrive from tenants, get assigned to the right person, and keep the tenant informed until the job is done.',
                    ],
                    [
                        'Documents',
                        'Leases, inventories and receipts live on private storage that is never web-reachable and only opens through an authorised, logged request.',
                    ],
                    [
                        'Financials',
                        'Income, expenses and arrears per property and per portfolio, with money handled as exact decimals rather than floating point.',
                    ],
                ] as $i => [$title, $body])
                    <div class="ph-card p-6">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-sm font-bold text-brand-700">
                            {{ $i + 1 }}
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Workspaces / multi-tenancy                                       --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="workspace" class="scroll-mt-20 border-b border-slate-200 bg-slate-50 py-16 sm:py-20">
        <div class="mx-auto grid w-full max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                    One workspace per landlord, and the walls are real
                </h2>
                <p class="mt-4 text-slate-600">
                    A managing agent running forty buildings is not one enormous account.
                    Each landlord gets an isolated workspace, and the separation is enforced
                    in the data layer rather than in the interface.
                </p>

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Every query is scoped', 'Tenant-owned records are filtered by a global scope, so a route that forgets to check ownership still cannot read another landlord\'s rows.'],
                        ['Membership is re-verified per request', 'The active workspace is validated against your memberships on every request, not trusted from the session or a hidden form field.'],
                        ['Switching is a deliberate act', 'Moving between workspaces is a CSRF-protected POST, so a link or an image tag cannot change where you are.'],
                    ] as [$title, $body])
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700" aria-hidden="true">
                                <svg class="size-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $body }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Role ladder: the same five roles the app actually enforces. --}}
            <div class="ph-card overflow-hidden p-0">
                <div class="border-b border-slate-200 bg-white px-5 py-3">
                    <p class="text-sm font-semibold text-slate-900">Your team, your rules</p>
                    <p class="text-xs text-slate-500">Each person gets a role per workspace.</p>
                </div>
                <ul class="divide-y divide-slate-200">
                    @foreach (\App\Enums\Role::cases() as $role)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <span @class([
                                'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md text-xs font-semibold',
                                'bg-brand-100 text-brand-700' => $role->usesBackOffice(),
                                'bg-slate-100 text-slate-600' => ! $role->usesBackOffice(),
                            ])>{{ \Illuminate\Support\Str::upper(substr($role->value, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900">{{ $role->label() }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-slate-600">{{ $role->description() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Roles                                                            --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="roles" class="scroll-mt-20 border-b border-slate-200 bg-white py-16 sm:py-20">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                    Access follows the job, not the seniority
                </h2>
                <p class="mt-4 text-slate-600">
                    Your accountant does not need to read a tenant's date of birth to reconcile
                    a payment, and your boiler engineer does not need your bank details. Roles
                    are enforced on the server for every request.
                </p>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-2">
                <div class="ph-card p-6">
                    <h3 class="text-base font-semibold text-slate-900">A permissions model, not a checkbox</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        {{ config('propertyhub.name') }} ships with a fixed catalogue of individual
                        permissions &mdash; invite staff, approve expenses, issue a refund, export
                        financials &mdash; and roles are just named groupings of them. Grant a
                        permission in one workspace and it has no effect in another, so one
                        compromised account cannot walk sideways into a different landlord.
                    </p>
                </div>

                <div class="ph-card p-6">
                    <h3 class="text-base font-semibold text-slate-900">Tenants get a door of their own</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        A tenant never touches the back office. They sign in to a portal scoped to
                        their own lease, where they can see rent due, download receipts and lease
                        documents, and raise a maintenance request &mdash; no landlord data in
                        sight, no way to guess another unit number.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Security                                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="security" class="scroll-mt-20 border-b border-slate-200 bg-slate-900 py-16 text-slate-300 sm:py-20">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-white">
                    Built so a mistake is boring
                </h2>
                <p class="mt-4 text-slate-400">
                    Security features that were designed in, rather than bolted on after an
                    incident report.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Tenant-scoped queries', 'Ownership is a database-level constraint on every tenant-owned table, so there is no "forgot to filter" class of bug.'],
                    ['Private file storage', 'Uploads live outside the public directory, are restricted by size and MIME type, and are only served through signed, authorised routes.'],
                    ['Immutable audit trail', 'Who joined a workspace, who changed a role, who viewed a document, who switched plans &mdash; recorded, timestamped and attributable.'],
                    ['Money as decimals', 'Amounts are exact decimals and arithmetic runs in BCMath, so totals never drift by a cent over thousands of transactions.'],
                    ['Real rate limiting', 'Login and password-reset endpoints are throttled per identifier and per address.'],
                    ['Stronger passwords', 'Minimum length, mixed case, a symbol, and a check against known-breached password lists.'],
                ] as $i => [$title, $body])
                    <div class="rounded-xl border border-slate-700/70 bg-slate-800/40 p-6">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-slate-700/70 text-slate-200" aria-hidden="true">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75M6 10.5h12A1.5 1.5 0 0 1 19.5 12v7.5A1.5 1.5 0 0 1 18 21H6a1.5 1.5 0 0 1-1.5-1.5V12A1.5 1.5 0 0 1 6 10.5Z" />
                            </svg>
                        </span>
                        <h3 class="mt-4 text-sm font-semibold text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Plans                                                            --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="plans" class="scroll-mt-20 border-b border-slate-200 bg-slate-50 py-16 sm:py-20">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                    Priced per portfolio, not per seat
                </h2>
                <p class="mt-4 text-slate-600">
                    Add the whole team on every plan. What changes is how much you can hold and
                    how much reporting you get.
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php($highlight = $plan->code === 'professional')
                    <div @class([
                        'ph-card relative flex flex-col p-6',
                        'ring-2 ring-brand-500' => $highlight,
                    ])>
                        @if ($highlight)
                            <span class="absolute -top-3 left-6 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                                Most popular
                            </span>
                        @endif

                        <h3 class="text-base font-semibold text-slate-900">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $plan->description }}</p>

                        <p class="mt-6 flex items-baseline gap-1.5">
                            <span class="text-4xl font-bold tracking-tight text-slate-900">{{ $plan->formattedPrice() }}</span>
                            <span class="text-sm text-slate-500">/ {{ $plan->intervalLabel() }}</span>
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            {{ $plan->trial_days }}-day free trial
                        </p>

                        <dl class="mt-6 space-y-2.5 border-t border-slate-200 pt-6 text-sm">
                            @foreach ([
                                'properties' => 'Properties',
                                'units' => 'Units',
                                'staff' => 'Staff accounts',
                                'documents' => 'Documents',
                            ] as $resource => $label)
                                @php($quota = $plan->quotaFor($resource))
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-slate-600">{{ $label }}</dt>
                                    <dd class="font-medium text-slate-900">
                                        {{ $quota === null ? 'Unlimited' : number_format($quota) }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-6 pt-2">
                            @if (Route::has('register'))
                                <x-button
                                    :href="route('register')"
                                    :variant="$highlight ? 'primary' : 'secondary'"
                                    class="w-full justify-center"
                                >
                                    Start with {{ $plan->name }}
                                </x-button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Closing CTA                                                      --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="bg-white py-16 sm:py-20">
        <div class="mx-auto w-full max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                Set up your first portfolio in an afternoon
            </h2>
            <p class="mt-4 text-slate-600">
                Create an account, name your business, pick a plan. You will have a workspace
                you can hand to your team the same day.
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if (Route::has('register'))
                    <x-button :href="route('register')" size="lg">Get started free</x-button>
                @endif

                <x-button :href="route('login')" variant="secondary" size="lg">Log in</x-button>
            </div>
        </div>
    </section>

@endsection
