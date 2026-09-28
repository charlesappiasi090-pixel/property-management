@props([
    'id',
    'title',
    'message' => null,
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'method' => 'DELETE',
    'variant' => 'danger',
    'loadingText' => 'Working...',

    /*
     * Default action for the hidden target form.
     *
     * Declared as a prop rather than read as an undefined variable. In an
     * anonymous component, an attribute that is not in `@props` lands in
     * `$attributes`, NOT in a local variable — so `{{ $action }}` without this
     * declaration evaluated to an undefined variable and threw, taking the
     * whole page to a 500. Individual triggers normally override it at click
     * time via `data-confirm-action`, so this default is only the fallback.
     */
    'action' => '#',
])

{{--
    Confirmation dialog built on the native <dialog> element.

    WHY NATIVE <dialog> INSTEAD OF A JAVASCRIPT MODAL
    ------------------------------------------------
    `showModal()` gives, for free, the things hand-rolled modals usually get
    wrong: focus trapping, focus restoration on close, Escape-to-dismiss,
    inert background content (so a screen reader cannot wander behind the
    modal), and correct `aria-modal` semantics.

    Progressive enhancement: without JavaScript the <form> still submits and
    the browser still validates `required`, so the destructive action is never
    trapped behind a failed script. This matters because the alternative
    failure mode — a user who cannot complete a delete and does not know why —
    is a support call, not a bug report.
--}}
<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    class="w-full max-w-md rounded-xl bg-transparent p-0 backdrop:bg-slate-900/50 backdrop:backdrop-blur-sm open:backdrop:bg-slate-900/50"
>
    <form method="dialog" class="rounded-xl bg-white shadow-xl" data-confirm-form>
        {{-- Close on backdrop click. `::backdrop` has no click target of its
             own, so the trick is to compare the dialog's own bounds. --}}
        <div data-dialog-shell class="p-6">
            <div class="flex items-start gap-4">
                @if ($variant === 'danger')
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                        <svg class="size-6 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                @else
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-100">
                        <svg class="size-6 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <h2 id="{{ $id }}-title" class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                    @if ($message)
                        <p class="mt-2 text-sm text-slate-600">{{ $message }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
            <button
                type="submit"
                value="cancel"
                data-dialog-cancel
                class="ph-btn-secondary"
            >
                {{ $cancelText }}
            </button>

            <button
                type="submit"
                value="confirm"
                data-dialog-confirm
                class="{{ $variant === 'danger' ? 'ph-btn-danger' : 'ph-btn-primary' }}"
            >
                {{ $confirmText }}
            </button>
        </div>
    </form>

    {{--
        The form that actually performs the action.

        It is a SIBLING of the `<dialog>`'s shell form, not nested inside it:
        a <dialog> is a top-level element and cannot legally contain the form
        that a surrounding <tbody> also has to contain. It is hidden because
        the dialog provides the affordance; the point is that it is a REAL form
        with a REAL CSRF token, so if the dialog markup is moved around the
        action still works.
    --}}
    <form method="POST" action="{{ $action }}" class="hidden" data-confirm-target>
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif
        {{ $slot }}
    </form>
</dialog>

@once
    {{--
        `@endpush` is required, and `@once` does not substitute for it: `@push`
        opens an output buffer (`startPush` -> `ob_start`) that only
        `stopPush` (from `@endpush`) closes.
    --}}
    @push('scripts')
        <script>
            /**
             * Confirmation dialogs.
             *
             * Wiring: any [data-confirm] element anywhere in the page carries
             * the target dialog id. Clicking it opens that <dialog> in modal
             * mode. Confirming submits [data-confirm-target]; cancelling just
             * closes it. If JS fails to load, [data-confirm] is an ordinary
             * <button type="button"> that does nothing — the destructive
             * action is simply unavailable rather than accidentally firing.
             */
            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-confirm]');

                if (!trigger) return;

                const dialog = document.getElementById(trigger.dataset.confirm);

                if (!dialog) return;

                event.preventDefault();

                const action = trigger.dataset.confirmAction || trigger.getAttribute('formaction') || trigger.form?.action;

                /*
                 * Which form gets submitted on confirm.
                 *
                 * A trigger may point at a form ANYWHERE on the page via the
                 * `form` attribute — needed when the form holds fields the
                 * user must be able to see and edit (a password confirmation,
                 * a typed reason), because the dialog's own target form is
                 * hidden and anything typed into it is invisible at the moment
                 * the user presses Confirm.
                 *
                 * The choice is captured HERE, at open time, and stashed on the
                 * dialog. Resolving it later is not possible: the confirm
                 * handler no longer has the trigger, only the dialog.
                 */
                const externalForm = trigger.getAttribute('form')
                    ? document.getElementById(trigger.getAttribute('form'))
                    : null;

                if (externalForm) {
                    dialog.dataset.confirmForm = externalForm.id;
                } else {
                    const target = dialog.querySelector('[data-confirm-target]');

                    if (target && action) {
                        target.setAttribute('action', action);
                    }
                }

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', '');
                }
            });

            // Any form with [data-confirm-form] is a dialog shell: submitting
            // it means the user picked a button, so close and act.
            document.addEventListener('submit', function (event) {
                const shell = event.target.closest('[data-confirm-form]');

                if (!shell) return;

                const dialog = shell.closest('dialog');
                const submitter = event.submitter;
                const isConfirm = submitter && submitter.hasAttribute('data-dialog-confirm');

                if (dialog) {
                    event.preventDefault();
                    dialog.close();
                }

                if (isConfirm) {
                    /*
                     * Prefer the form the trigger pointed at, falling back to
                     * the dialog's own hidden target form.
                     */
                    const target = dialog?.dataset.confirmForm
                        ? document.getElementById(dialog.dataset.confirmForm)
                        : dialog?.querySelector('[data-confirm-target]');

                    if (target) {
                        /*
                         * The target form already carries a Blade `@csrf`
                         * field, so there is normally nothing to attach here.
                         * Appending a SECOND `_token` produced a form with two
                         * identical values, which PHP's parser collapses to
                         * the last one — it happened to work, but only by
                         * accident, and it left a duplicate in the DOM.
                         *
                         * The fallback is kept for a caller that renders the
                         * dialog without `@csrf`: posting an unauthenticated
                         * request fails as a bare 419, which is the hardest
                         * error in the whole app to diagnose.
                         */
                        if (!target.querySelector('input[name="_token"]')) {
                            const tokenField = document.createElement('input');
                            tokenField.type = 'hidden';
                            tokenField.name = '_token';
                            tokenField.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
                            target.appendChild(tokenField);
                        }

                        target.submit();
                    }
                }
            });

            // Clicking the backdrop (outside the dialog's own box) dismisses.
            document.addEventListener('click', function (event) {
                /*
                 * Only the `<dialog>` ELEMENT itself is a backdrop click. The
                 * `:has()`-free guard `event.target !== dialog` is what stops a
                 * click on the title, the message, or the buttons from being
                 * mistaken for one — without it, clicking "Cancel" would also
                 * register as a backdrop dismissal and the handler below would
                 * try to close an already-closing dialog.
                 */
                const dialog = event.target.closest('dialog');

                if (!dialog || event.target !== dialog) return;

                const shell = dialog.querySelector('[data-dialog-shell]');

                if (!shell) return;

                const box = shell.getBoundingClientRect();
                const inside = event.clientX >= box.left && event.clientX <= box.right
                    && event.clientY >= box.top && event.clientY <= box.bottom;

                if (!inside) {
                    dialog.close();
                }
            });
        </script>
    @endpush
@endonce
