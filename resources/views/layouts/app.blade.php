<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Read by the confirm-dialog script to attach a CSRF token at submit time. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') &middot; {{ config('propertyhub.name') }}</title>

    {{--
        The sidebar's open state lives on <body> as an attribute, and this
        resets it. `pagehide` covers the bfcache case: on a Safari/Chrome back
        navigation the whole page — including this script's IIFE — can be
        restored from cache without re-running, which would otherwise leave a
        drawer flagged open that no handler can close.
    --}}
    <script>
        addEventListener('pagehide', () => {
            document.body.removeAttribute('data-sidebar-open');
        });
    </script>

    <x-stylesheets />
</head>

<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased">
    {{-- First tab stop on every page. --}}
    <a href="#main-content" class="ph-sr-only ph-sr-only-focusable">
        Skip to main content
    </a>

    <x-layout.app-shell>
        @yield('content')
    </x-layout.app-shell>

    @stack('scripts')
</body>
</html>
