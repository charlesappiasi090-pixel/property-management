@extends('layouts.app')

@section('title', 'Plans')

@section('content')
    <div class="mb-8 text-center">
        <x-button :href="route('app.subscription.show')" variant="ghost" size="sm" class="mb-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to billing
        </x-button>

        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Choose the plan that fits</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600">
            Every plan includes the full feature set during your trial. You can change or cancel at any time.
        </p>
    </div>

    <x-flash-messages />

    @if ($plans->isEmpty())
        {{-- An empty catalogue must say so plainly rather than rendering an
             empty grid the user reads as a loading failure. --}}
        <section class="ph-card">
            <x-empty-state
                icon="credit-card"
                title="No plans available"
                message="There are no purchasable plans configured for this environment. Contact support if you expected to see one."
            />
        </section>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = $plan->id === $currentPlanId;
                    $highlight = $plan->code === $plans->sortBy('sort_order')->first()?->code;
                @endphp

                <section
                    @class([
                        'ph-card relative flex flex-col',
                        'ring-2 ring-brand-600' => $isCurrent,
                    ])
                >
                    @if ($isCurrent)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">
                            Your current plan
                        </span>
                    @elseif ($highlight)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-white">
                            Most popular
                        </span>
                    @endif

                    <div class="ph-card-body flex flex-1 flex-col">
                        <h3 class="text-base font-semibold text-slate-900">{{ $plan->name }}</h3>

                        @if ($plan->tagline)
                            <p class="mt-1 text-sm text-slate-600">{{ $plan->tagline }}</p>
                        @endif

                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="text-3xl font-bold tracking-tight text-slate-900">
                                {{ $plan->formattedPrice() }}
                            </span>
                            <span class="text-sm text-slate-500">/ {{ $plan->intervalLabel() }}</span>
                        </p>

                        @if ($plan->trial_days > 0)
                            <p class="mt-1 text-sm text-emerald-600">
                                {{ $plan->trial_days }}-day free trial
                            </p>
                        @else
                            <p class="mt-1 text-sm text-slate-500">No trial</p>
                        @endif

                        {{-- Quotas ------------------------------------------------------ --}}
                        <dl class="mt-6 flex-1 space-y-2 border-t border-slate-200 pt-5 text-sm">
                            @foreach (['properties' => 'Properties', 'units' => 'Units', 'staff' => 'Team seats', 'documents' => 'Documents'] as $key => $label)
                                @php $quota = $plan->quotaFor($key); @endphp

                                <div class="flex items-center justify-between gap-3">
                                    <dt class="flex items-center gap-2 text-slate-600">
                                        <x-icon name="check-circle" class="size-4 shrink-0 text-emerald-500" />
                                        {{ $label }}
                                    </dt>
                                    <dd class="font-semibold text-slate-900 tabular-nums">
                                        {{ $quota === null ? 'Unlimited' : number_format($quota) }}
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($plan->description)
                            <p class="mt-5 border-t border-slate-200 pt-4 text-sm text-slate-600">
                                {{ $plan->description }}
                            </p>
                        @endif
                    </div>

                    <div class="border-t border-slate-200 p-5">
                        @if ($isCurrent)
                            <x-button variant="secondary" class="w-full" disabled>
                                Current plan
                            </x-button>
                        @else
                            {{--
                                No checkout action yet. Rendering a disabled
                                "Choose plan" button would imply a purchase
                                flow exists; saying what is actually true is
                                more useful than a dead control.
                            --}}
                            <x-button variant="secondary" class="w-full" disabled>
                                Available at checkout
                            </x-button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-slate-400">
            Card payments are not enabled yet. Switching plans will be available once the payment gateway is connected.
        </p>
    @endif
@endsection
