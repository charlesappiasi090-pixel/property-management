@extends('layouts.app')

@section('title', 'Staff')

@section('headerActions')
    @if ($canManage)
        <x-button :href="route('app.staff.create')" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a team member
        </x-button>
    @endif
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Team</h2>
        <p class="mt-1 text-sm text-slate-600">
            Everyone with access to {{ $business->name }}. Roles decide what each person can see and change.
        </p>
    </div>

    <section class="ph-card">
        @if ($members->isEmpty())
            <x-empty-state
                icon="users"
                title="No team members yet"
                message="You're the only one here so far. Add colleagues and set their access level."
            >
                @if ($canManage)
                    <x-button :href="route('app.staff.create')" size="sm">Add a team member</x-button>
                @endif
            </x-empty-state>
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">Team members of {{ $business->name }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Role</th>
                            <th scope="col">Last seen</th>
                            <th scope="col" class="text-right">
                                <span class="ph-sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($members as $member)
                            @php
                                /*
                                 * $member->roles is already constrained to this
                                 * business by the controller's `where` on
                                 * model_has_roles.business_id, so it cannot leak
                                 * a role the person holds at another landlord.
                                 */
                                $role = $member->roles->first();
                                $isSelf = $member->is(auth()->user());
                            @endphp

                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                            {{ $member->initials() }}
                                        </span>

                                        <div class="min-w-0">
                                            <p class="font-medium text-slate-900">
                                                {{ $member->name }}
                                                @if ($isSelf)
                                                    <span class="ml-1 text-xs font-normal text-slate-400">(you)</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ $member->email }}
                                                @if ($member->pivot->job_title)
                                                    <span class="text-slate-400">&middot; {{ $member->pivot->job_title }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($role)
                                        <x-status-badge
                                            :label="$role->name === 'owner' ? 'Owner' : \App\Enums\Role::tryFrom($role->name)?->label()"
                                            class="bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200"
                                        />
                                    @else
                                        <span class="text-sm text-slate-400">No role</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap text-slate-500">
                                    @if ($member->last_login_at)
                                        <time datetime="{{ $member->last_login_at->toIso8601String() }}">
                                            {{ $member->last_login_at->diffForHumans() }}
                                        </time>
                                    @else
                                        Never
                                    @endif
                                </td>

                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{--
                                            The edit screen is gated on
                                            `staff.update`, NOT `staff.view`. A
                                            property manager can see the roster
                                            but cannot change anyone's role, so
                                            offering them the link would only
                                            earn a 403. Gate the button on the
                                            permission the route enforces.
                                        --}}
                                        @canIn(\App\Enums\PermissionName::STAFF_UPDATE)
                                            <x-button
                                                :href="route('app.staff.edit', $member)"
                                                variant="secondary"
                                                size="sm"
                                            >
                                                Change role
                                            </x-button>
                                        @endcanIn

                                        {{--
                                            Removal is hidden for yourself: the
                                            controller rejects it with a clearer
                                            message, but a button that cannot
                                            succeed is just a way to lose trust in
                                            the UI.
                                        --}}
                                        @canIn(\App\Enums\PermissionName::STAFF_REMOVE)
                                            @unless ($isSelf)
                                                <x-button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-rose-600 hover:bg-rose-50"
                                                    data-confirm="remove-member-{{ $member->id }}"
                                                    data-confirm-action="{{ route('app.staff.destroy', $member) }}"
                                                >
                                                    Remove
                                                </x-button>
                                            @endunless
                                        @endcanIn
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{--
                Confirmation dialogs live OUTSIDE the table, after it.

                A <dialog> is a top-level element: HTML's content model does not
                allow it inside <tbody>, and browsers "fix" the invalid nesting by
                hoisting the element out of the table on their own. That silently
                moved the dialogs in the markup, which detached them from the
                rows they belonged to and made the table structure depend on
                undocumented parser behaviour.

                They are emitted here in the same order as the rows, so the
                pairing between a row's Remove button and its dialog is still
                obvious in the source.
            --}}
            @foreach ($members as $member)
                <x-confirm-dialog
                    id="remove-member-{{ $member->id }}"
                    title="Remove {{ $member->name }}?"
                    message="They will immediately lose access to {{ $business->name }}. Their past activity stays in the audit trail."
                    confirm-text="Remove from team"
                    :action="route('app.staff.destroy', $member)"
                />
            @endforeach

            @if ($members->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $members->links() }}
                </div>
            @endif
        @endif
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Role reference                                                     --}}
    {{-- Shown read-only so an owner can see what each role actually means
         before assigning one. This is the same matrix the seeder applies. --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card mt-6">
        <div class="ph-card-header">
            <h3 class="ph-card-title">What each role can do</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach (\App\Enums\Role::cases() as $role)
                    <div>
                        <dt class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                            {{ $role->label() }}
                            @unless ($role->isAssignableByOwner())
                                <span class="ph-badge bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">
                                    not assignable here
                                </span>
                            @endunless
                        </dt>
                        <dd class="mt-1 text-sm text-slate-600">{{ $role->description() }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endsection
