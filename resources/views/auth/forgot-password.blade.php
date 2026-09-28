@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Forgot your password?</h1>
                <p class="mt-1.5 text-sm text-slate-600">
                    Enter your email and we'll send a link to reset it.
                </p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                {{--
                    Status is announced, not just coloured. The message is
                    deliberately identical whether or not the address exists, so
                    this form cannot be used to discover which emails are
                    registered.
                --}}
                @if (session('status'))
                    <div
                        role="status"
                        class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    >
                        {{ session('status') }}
                    </div>
                @endif

                <x-flash-messages />

                <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
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

                    <x-button type="submit" class="w-full" loading-text="Sending link...">
                        Email password reset link
                    </x-button>
                </form>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-slate-600">
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-700">
                Back to log in
            </a>
        </p>
    </div>
@endsection
