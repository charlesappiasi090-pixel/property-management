@extends('layouts.guest')

@section('title', 'Create your account')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Create your account</h1>
                <p class="mt-1.5 text-sm text-slate-600">
                    Start a 14-day trial. No card required.
                </p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <x-flash-messages />

                <form method="POST" action="{{ route('register') }}" class="mt-2 space-y-5">
                    @csrf

                    <x-text-field
                        name="name"
                        type="text"
                        label="Full name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Jane Doe"
                    />

                    <x-text-field
                        name="email"
                        type="email"
                        label="Email address"
                        required
                        autocomplete="username"
                        placeholder="you@example.com"
                    />

                    <x-text-field
                        name="phone"
                        type="tel"
                        label="Phone number"
                        autocomplete="tel"
                        placeholder="+254 700 000 000"
                    >
                        {{-- Optional in validation, so the field says so rather
                             than leaving a required-looking asterisk on a
                             control that will accept an empty value. --}}
                        <p class="mt-1.5 text-xs text-slate-500">Optional. Used for staff and maintenance dispatch only.</p>
                    </x-text-field>

                    <x-text-field
                        name="password"
                        type="password"
                        label="Password"
                        required
                        autocomplete="new-password"
                    />

                    <x-text-field
                        name="password_confirmation"
                        type="password"
                        label="Confirm password"
                        required
                        autocomplete="new-password"
                    />

                    {{--
                        `Rules\Password::defaults()` in the controller is the
                        single source of truth for strength, and this hint is a
                        plain-language mirror of it. If the defaults change,
                        both the message and the validation change together in
                        the controller; this text is guidance, not a second
                        rule.
                    --}}
                    <p class="-mt-2 text-xs text-slate-500">
                        Use at least 8 characters.
                    </p>

                    <x-button type="submit" class="w-full" loading-text="Creating your account...">
                        Create account
                    </x-button>
                </form>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">
                Log in
            </a>
        </p>
    </div>
@endsection
