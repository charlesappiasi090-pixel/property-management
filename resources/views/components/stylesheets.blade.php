{{--
    Stylesheet include for a page shell.

    `@vite()` throws when `public/build/manifest.json` is missing, which is the
    correct production behaviour but makes every page a 500 on a fresh clone
    that has never been through `npm install && npm run build`. This component
    centralises the fallback so each layout does not repeat the same
    `file_exists` pair, and so there is exactly one place to change if the
    build output ever moves.

    The fallback is `public/css/prebuild.css`, a hand-written approximation of
    the handful of classes the public pages use. It is a stopgap, not a second
    source of truth: once a real build exists, Blade stops loading it and the
    compiled Tailwind output takes over. Do not add product styling there.
--}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <link rel="stylesheet" href="{{ asset('css/prebuild.css') }}">
@endif
