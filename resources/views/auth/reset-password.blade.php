@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Choose a new password</h1>
                <p class="mt-1.5 text-sm text-slate-600">
                    This replaces the password on your PropertyHub account.
                </p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <x-flash-messages />

                {{--
                    The `token` and `email` inputs are hidden rather than dropped.
                    They are what links this submission back to the reset request
                    in the database; the token is not read from the URL on the
                    server side, because then any page could be made to submit
                    against someone else's reset.
                --}}
                <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">

                    <x-text-field
                        name="email"
                        type="email"
                        label="Email address"
                        :value="old('email', $email)"
                        required
                        autofocus
                        autocomplete="username"
                    />

                    <x-text-field
                        name="password"
                        type="password"
                        label="New password"
                        required
                        autocomplete="new-password"
                    />

                    <x-text-field
                        name="password_confirmation"
                        type="password"
                        label="Confirm new password"
                        required
                        autocomplete="new-password"
                    />

                    <x-button type="submit" class="w-full" loading-text="Updating password...">
                        Reset password
                    </x-button>
                </form>
            </div>
        </div>
    </div>
@endsection
