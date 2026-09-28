<!DOCTYPE html>
{{--
    Marketing layout: the public, full-bleed shell used by `/`.

    Separate from `layouts/guest.blade.php` because that one is deliberately
    narrow - it centres a single card in a viewport-height flex column, which is
    right for a login form and wrong for a scrolling landing page. Reusing it
    would have squeezed the page between `justify-center` and the max-w-7xl
    header padding.

    The Vite decision lives in the `x-stylesheets` component, which falls back
    to a hand-written stylesheet while `public/build/manifest.json` is absent.
    See resources/views/components/stylesheets.blade.php.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('propertyhub.name')) &middot; {{ config('propertyhub.tagline') }}</title>

    <meta name="description" content="@yield('description', 'Property and tenant management for landlords, property managers and their staff - workspaces, roles, leases, rent and maintenance in one place.')">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <x-stylesheets />
</head>

<body class="min-h-full bg-white font-sans text-slate-800 antialiased">
    {{-- First tab stop on every page. --}}
    <a href="#main-content" class="ph-sr-only ph-sr-only-focusable">
        Skip to main content
    </a>

    <div class="flex min-h-full flex-col">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                        PH
                    </span>
                    <span class="text-base font-semibold text-slate-900">
                        {{ config('propertyhub.name') }}
                    </span>
                </a>

                <nav class="hidden items-center gap-7 md:flex" aria-label="Sections">
                    @yield('nav')
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                        {{-- Routed through `home` so this cannot start a
                             redirect loop: `home` is state-aware. --}}
                        <x-button :href="route('home')" variant="secondary" size="sm">
                            My workspace
                        </x-button>
                    @else
                        <x-button :href="route('login')" variant="ghost" size="sm" class="hidden sm:inline-flex">
                            Log in
                        </x-button>

                        @if (Route::has('register'))
                            <x-button :href="route('register')" size="sm">Get started</x-button>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main id="main-content" class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">
                                PH
                            </span>
                            <span class="text-sm font-semibold text-slate-900">
                                {{ config('propertyhub.name') }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">
                            {{ config('propertyhub.tagline') }}
                        </p>
                    </div>

                    <nav class="flex flex-wrap gap-x-8 gap-y-2 text-sm" aria-label="Footer">
                        <a href="{{ route('home') }}#roles" class="text-slate-600 hover:text-slate-900">Roles</a>
                        <a href="{{ route('home') }}#workspace" class="text-slate-600 hover:text-slate-900">Workspaces</a>
                        <a href="{{ route('home') }}#security" class="text-slate-600 hover:text-slate-900">Security</a>
                        <a href="{{ route('home') }}#plans" class="text-slate-600 hover:text-slate-900">Plans</a>

                        @guest
                            <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Log in</a>
                        @endguest
                    </nav>
                </div>

                <p class="mt-8 border-t border-slate-200 pt-6 text-xs text-slate-500">
                    &copy; {{ now()->year }} {{ config('propertyhub.name') }}.
                    <span class="hidden sm:inline">All rights reserved.</span>
                </p>
            </div>
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
