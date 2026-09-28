@extends('layouts.app')

@section('title', 'Messages')

@section('headerActions')
    <x-button :href="route('app.messages.create')" size="sm">
        <x-icon name="plus" class="size-4" />
        New message
    </x-button>
@endsection

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Messages</h2>
        <p class="mt-1 text-sm text-slate-600">
            Communications with your tenants.
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Filters                                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="border-b border-slate-200 p-4 mb-4">
        <form method="GET" action="{{ route('app.messages.index') }}" class="flex flex-wrap items-end gap-3">
            <x-text-field
                name="search"
                label="Search subject"
                :value="$search"
                placeholder="Enter subject text"
                autocomplete="off"
            />

            <x-select-field
                name="unread_only"
                label="Unread only"
                :selected="$unreadOnly"
                :options="['' => 'All', '1' => 'Only unread']"
            />

            <x-select-field
                name="read_only"
                label="Read only"
                :selected="$readOnly"
                :options="['' => 'All', '1' => 'Only read']"
            />

            <x-button type="submit" variant="secondary">Apply</x-button>

            @if ($search || $unreadOnly || $readOnly)
                <x-button :href="route('app.messages.index')" variant="ghost">Clear</x-button>
            @endif
        </form>
    </div>

    @if ($messages->isEmpty())
        <x-empty-state
            icon="message-circle"
            title="No messages match"
            message="Try adjusting the filters above, or create the first message to get started."
        >
            @can('send', \App\Models\Message::class)
                <x-button :href="route('app.messages.create')" size="sm">Create first message</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="ph-scroll overflow-x-auto">
            <table class="ph-table">
                <caption class="ph-sr-only">Messages for {{ app('business')->name }}</caption>

                <thead>
                    <tr>
                        <th scope="col">Recipient</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">
                            <span class="ph-sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($messages as $message)
                        <tr class="{{ $message->isUnread() ? 'font-medium text-slate-900' : 'text-slate-500' }}">
                            <td>
                                <a
                                    href="{{ route('app.tenants.show', $message->tenant) }}"
                                    class="hover:text-brand-700 hover:underline"
                                >
                                    {{ $message->tenant->name }}
                                </a>
                            </td>

                            <td>
                                <a
                                    href="{{ route('app.messages.show', $message) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                >
                                    {{ $message->subject }}
                                </a>
                            </td>

                            <td>
                                @if ($message->isUnread())
                                    <x-status-badge
                                        label="Unread"
                                        class="bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200"
                                    />
                                @else
                                    <x-status-badge
                                        label="Read"
                                        class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                    />
                                @endif
                            </td>

                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('delete', $message)
                                        <x-button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="delete-message-{{ $message->id }}"
                                            data-confirm-action="{{ route('app.messages.destroy', $message) }}"
                                        >
                                            <span class="ph-sr-only">Delete message {{ $message->id }}</span>
                                            <x-icon name="trash" class="size-4" />
                                        </x-button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($messages->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $messages->links() }}
            </div>
        @endif
    @endif
@endsection