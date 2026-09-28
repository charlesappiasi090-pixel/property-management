@extends('layouts.guest')

@section('title', 'Confirm password')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Confirm your password</h1>
                <p class="mt-1.5 text-sm text-slate-600">
                    This is a protected area. Please confirm your password before continuing.
                </p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <x-flash-messages />

                <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
                    @csrf

                    <x-text-field
                        name="password"
                        type="password"
                        label="Password"
                        required
                        autofocus
                        autocomplete="current-password"
                    />

                    <x-button type="submit" class="w-full" loading-text="Confirming...">
                        Confirm
                    </x-button>
                </form>
            </div>
        </div>
    </div>
@endsection
