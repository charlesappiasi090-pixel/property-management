<div class="rounded-lg border border-red-200 bg-white">
    <div class="px-6 py-6 sm:px-8">
        <p class="text-sm text-slate-600">
            Permanently removes your login. Records you created — audit entries, staff
            actions, billing history — are retained for the businesses you worked for,
            but you will no longer be able to sign in.
        </p>

        {{--
            The password field lives here, in its own form, NOT inside the
            confirmation dialog.

            Reason: the dialog's target form is rendered `hidden` and submitted
            by script. A field typed into a hidden form is invisible to the user
            when the confirm button is pressed, so they would have no way to
            know which password was being used. Keeping the field visible and
            letting the dialog confirm the submission is both clearer and
            keyboard/screen-reader friendly.
        --}}
        <form
            id="delete-account-form"
            method="POST"
            action="{{ route('profile.destroy') }}"
            class="mt-5 space-y-4"
        >
            @csrf
            @method('DELETE')

            <x-text-field
                name="password"
                type="password"
                label="Confirm with your password"
                required
                autocomplete="current-password"
                :error-messages="$errors->userDeletion->get('password')"
            />
        </form>

        <div class="mt-5">
            <x-confirm-dialog
                id="confirm-user-deletion"
                title="Delete your account?"
                message="This cannot be undone. Your login will be removed immediately. If you are the only owner of a business, transfer ownership first — otherwise nobody would be able to manage it."
                confirm-text="Delete account"
                cancel-text="Keep my account"
                :action="route('profile.destroy')"
                method="DELETE"
                variant="danger"
            >
                {{--
                    The trigger lives in the SLOT of the dialog, so the trigger
                    and the dialog it opens are emitted together and the id
                    cannot drift apart. `data-confirm` names the dialog.

                    `form` associates this button with the visible password form
                    above without nesting it, which is how a `type="button"`
                    control submits a form elsewhere on the page.
                --}}
                <x-button
                    type="button"
                    variant="danger"
                    data-confirm="confirm-user-deletion"
                    form="delete-account-form"
                >
                    Delete account
                </x-button>
            </x-confirm-dialog>
        </div>
    </div>
</div>
