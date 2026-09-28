@extends('layouts.app')

@section('title', 'Your account')

@section('content')
    {{--
        Three independent concerns, three separate forms, three separate
        submit targets.

        They are kept in separate forms rather than combined on purpose: each
        one carries its own CSRF token, its own error bag, and its own password
        confirmation. A single combined form would make "save my email" also
        re-hash the password and re-confirm account deletion, and a validation
        error in one would block the others.
    --}}
    <div class="mx-auto w-full max-w-3xl space-y-6">

        <x-flash-messages />

        <div>
            <h2 class="text-lg font-semibold text-slate-900">Profile information</h2>
            <p class="mt-1 text-sm text-slate-600">
                Your name and email address. Changing your email requires re-verification.
            </p>
        </div>

        @include('profile.partials.update-profile-information-form')

        <div class="pt-2">
            <h2 class="text-lg font-semibold text-slate-900">Update password</h2>
            <p class="mt-1 text-sm text-slate-600">
                Use a long, unique password. This account can reach tenancy documents.
            </p>
        </div>

        @include('profile.partials.update-password-form')

        <div class="pt-2">
            <h2 class="text-lg font-semibold text-red-700">Delete account</h2>
            <p class="mt-1 text-sm text-slate-600">
                Permanently removes your login. This cannot be undone.
            </p>
        </div>

        @include('profile.partials.delete-user-form')
    </div>
@endsection
