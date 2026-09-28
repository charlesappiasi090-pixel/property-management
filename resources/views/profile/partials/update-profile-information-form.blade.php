@php
    use Illuminate\Contracts\Auth\MustVerifyEmail;
@endphp

<div class="ph-card">
    <div class="px-6 py-6 sm:px-8">
        {{-- `send-verification` is a SIBLING of this form, not nested inside
             it. See the note on the empty form below. --}}
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <x-text-field
                name="name"
                type="text"
                label="Full name"
                :value="old('name', $user->name)"
                required
                autofocus
                autocomplete="name"
            />

            <x-text-field
                name="email"
                type="email"
                label="Email address"
                :value="old('email', $user->email)"
                required
                autocomplete="username"
            />

            @if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm text-amber-900">
                        <span class="font-medium">Your email address is unverified.</span>
                        Some notifications are withheld until it is confirmed.
                    </p>

                    <button
                        type="submit"
                        form="send-verification"
                        class="mt-2 text-sm font-medium text-amber-900 underline underline-offset-2 hover:text-amber-700"
                    >
                        Resend the verification email
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p role="status" class="mt-2 text-sm font-medium text-emerald-700">
                            A new verification link has been sent.
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-4">
                <x-button type="submit" loading-text="Saving...">Save changes</x-button>

                @if (session('status') === 'profile-updated')
                    {{-- `role="status"` announces the change to a screen reader.
                         The `x-show` timer only hides it visually, and it is
                         removed from the a11y tree when hidden. --}}
                    <p role="status" class="text-sm font-medium text-emerald-700">
                        Saved.
                    </p>
                @endif
            </div>
        </form>
    </div>
</div>

{{--
    Empty form targeted by `form="send-verification"` on the resend button.

    It must live OUTSIDE the profile-update form: nested forms are invalid HTML
    and browsers drop the inner element, which would leave the button pointing
    at nothing. It carries its own CSRF token and is never rendered with fields.
--}}
<form id="send-verification" method="POST" action="{{ route('verification.send') }}" class="hidden">
    @csrf
</form>
