@extends('layouts.guest')

@section('title', 'Verify your email')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <div class="ph-card">
            <div class="border-b border-slate-200 px-6 py-5 sm:px-8">
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Verify your email</h1>
                <p class="mt-1.5 text-sm text-slate-600">
                    We've sent a verification link to
                    <span class="font-medium text-slate-800">{{ $email }}</span>.
                    Follow it to finish setting up your account.
                </p>
            </div>

            <div class="px-6 py-6 sm:px-8">
                <x-flash-messages />

                @if (session('status') === 'verification-link-sent')
                    <div
                        role="status"
                        class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    >
                        A new verification link has been sent to your email address.
                    </div>
                @endif

                <div class="flex items-center justify-between gap-4">
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <x-button type="submit" variant="secondary" loading-text="Sending...">
                            Resend verification email
                        </x-button>
                    </form>

                    {{--
                        Signing out is the escape hatch. Without it a user whose
                        mail is misconfigured is trapped on this page, because
                        `verified` middleware will bounce them straight back.
                    --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" variant="ghost">Log out</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
