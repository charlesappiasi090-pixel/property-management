<div class="ph-card">
    <div class="px-6 py-6 sm:px-8">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            {{--
                Errors are read from the `updatePassword` bag, which
                `PasswordController::update()` sets via `validateWithBag()`.

                That separation is deliberate: the page carries three forms, and
                a shared bag would paint a password error onto the profile form
                above. `$errors->updatePassword` is a ViewErrorBag, so it
                resolves to an empty bag rather than null when the name is
                absent.
            --}}

            <x-text-field
                name="current_password"
                type="password"
                label="Current password"
                required
                autocomplete="current-password"
                :error-messages="$errors->updatePassword->get('current_password')"
            />

            <x-text-field
                name="password"
                type="password"
                label="New password"
                required
                autocomplete="new-password"
                :error-messages="$errors->updatePassword->get('password')"
            />

            <x-text-field
                name="password_confirmation"
                type="password"
                label="Confirm new password"
                required
                autocomplete="new-password"
                :error-messages="$errors->updatePassword->get('password_confirmation')"
            />

            {{--
                Mirrors `Password::defaults()` in AppServiceProvider. Guidance
                only — the enforced rule lives in the controller, so the two
                cannot disagree about what is actually required.
            --}}
            <p class="-mt-2 text-xs text-slate-500">
                At least 12 characters, with upper and lower case, a number, and a symbol.
            </p>

            <div class="flex flex-wrap items-center gap-4">
                <x-button type="submit" loading-text="Updating...">Update password</x-button>

                @if (session('status') === 'password-updated')
                    <p role="status" class="text-sm font-medium text-emerald-700">
                        Password updated.
                    </p>
                @endif
            </div>
        </form>
    </div>
</div>
