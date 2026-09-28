{{--
    Tenant landing page for Phase 1.

    This is NOT a stub pretending to be a portal. The tenant portal is Phase 14;
    at this point a tenant-only account genuinely has no work to do, and the
    honest thing is to say so and offer the two actions that DO work (switch
    business, sign out) rather than shipping an empty dashboard with fake zero
    figures on it.

    The back-office shell is deliberately not used: it renders staff navigation
    a tenant has no permission to use.
--}}
@extends('layouts.guest', ['title' => 'Your tenant portal'])

<div class="mx-auto w-full max-w-lg text-center">
    <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-brand-100">
        <x-icon name="home" class="size-7 text-brand-600" />
    </div>

    <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">
        Your tenant portal is on its way
    </h1>

    <p class="mt-3 text-sm text-slate-600">
        You're set up as a tenant
        @if ($business = app(\App\Support\Tenancy\BusinessContext::class)->business())
            at <span class="font-medium text-slate-800">{{ $business->displayName() }}</span>
        @endif,
        but the page where you'll view your lease, pay rent and raise maintenance
        requests hasn't been built yet. It arrives in a later release.
    </p>

    <p class="mt-3 text-sm text-slate-600">
        Nothing is wrong with your account, and you haven't missed a step.
    </p>

    @if (auth()->user()->availableBusinesses()->count() > 1)
        <div class="mt-6 text-left">
            <p class="text-sm font-medium text-slate-700">
                If you also work for a property manager, you can switch to that workspace:
            </p>

            <div class="mt-3 space-y-2">
                @foreach (auth()->user()->availableBusinesses()->get() as $option)
                    <form method="POST" action="{{ route('business.switch') }}">
                        @csrf
                        <input type="hidden" name="business_id" value="{{ $option->id }}">

                        <x-button
                            type="submit"
                            :variant="$option->id === $business?->id ? 'secondary' : 'primary'"
                            class="w-full justify-start"
                        >
                            Switch to {{ $option->name }}
                        </x-button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-8">
        @csrf
        <x-button type="submit" variant="ghost">Sign out</x-button>
    </form>
</div>
