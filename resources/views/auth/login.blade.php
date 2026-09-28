@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Welcome back</h1>
                <p class="mt-1.5 text-sm text-slate-600">Log in to manage your properties.</p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <x-flash-messages />

                <form method="POST" action="{{ route('login') }}" class="mt-2 space-y-5">
                    @csrf

                    <x-text-field
                        name="email"
                        type="email"
                        label="Email address"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="you@example.com"
                    />

                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <x-text-field
                                name="password"
                                type="password"
                                label="Password"
                                required
                                autocomplete="current-password"
                                class="flex-1"
                            />
                        </div>

                        @if (Route::has('password.request'))
                            <div class="mt-2 text-right">
                                <a
                                    href="{{ route('password.request') }}"
                                    class="text-sm font-medium text-brand-600 hover:text-brand-700"
                                >
                                    Forgot your password?
                                </a>
                            </div>
                        @endif
                    </div>

                    {{--
                        Laravel Breeze ships a "remember me" checkbox. It is
                        deliberately omitted: a property-management session
                        grants access to tenancy documents and rent records, and
                        a shared or unattended machine should not stay signed
                        in. Session lifetime is the control here.
                    --}}

                    <x-button type="submit" class="w-full" loading-text="Signing in...">
                        Log in
                    </x-button>
                </form>
            </div>
        </div>

        @if (Route::has('register'))
            <p class="mt-6 text-center text-sm text-slate-600">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:text-brand-700">
                    Get started
                </a>
            </p>
        @endif
    </div>
@endsection
