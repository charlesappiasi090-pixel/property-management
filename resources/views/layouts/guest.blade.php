<!DOCTYPE html>
{{--
    Guest layout: the chrome for anything reachable without an active tenant —
    login, register, password reset, onboarding, and the tenant pending page.

    Written with `@yield`, not `$slot`, to match `layouts/app.blade.php`. These
    two are used with `@extends(...)` + `@section('content')`; a layout that
    reads `$slot` compiles fine but renders an empty page, because `$slot`
    only exists for anonymous components. That mismatch is what made the whole
    auth surface 500 behind a broken route name while still passing
    `php artisan view:cache` — Blade cache validates syntax, not the variables
    a template reads.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('propertyhub.name')) &middot; {{ config('propertyhub.name') }}</title>

    <x-stylesheets />
</head>

<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased">
    {{-- First tab stop on every page. --}}
    <a href="#main-content" class="ph-sr-only ph-sr-only-focusable">
        Skip to main content
    </a>

    <div class="flex min-h-full flex-col">
        <header class="shrink-0 border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                {{-- `home`, not a `welcome` route name: `/` is registered as
                     `home` and is state-aware (guests see the marketing page,
                     authenticated users are routed onward). --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                        PH
                    </span>
                    <span class="text-base font-semibold text-slate-900">
                        {{ config('propertyhub.name') }}
                    </span>
                </a>

                <nav class="flex items-center gap-2" aria-label="Account">
                    @auth
                        {{-- Routed through `home` rather than straight to the
                             dashboard: `home` sends tenant-only users to the
                             portal and users with no business to onboarding,
                             so this link cannot start a redirect loop. --}}
                        <x-button :href="route('home')" variant="secondary" size="sm">
                            My workspace
                        </x-button>
                    @else
                        <x-button :href="route('login')" variant="ghost" size="sm">Log in</x-button>

                        @if (Route::has('register'))
                            <x-button :href="route('register')" size="sm">Get started</x-button>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main-content" class="flex flex-1 flex-col justify-center px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            @yield('content')
        </main>

        <footer class="shrink-0 border-t border-slate-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <p class="text-center text-xs text-slate-500">
                    &copy; {{ now()->year }} {{ config('propertyhub.name') }}.
                    <span class="hidden sm:inline">All rights reserved.</span>
                </p>
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
