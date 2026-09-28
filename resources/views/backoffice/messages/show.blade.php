@extends('layouts.app')

@section('title', 'Message – {{ $message->subject }}')

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.messages.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to inbox
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Message – {{ $message->subject }}
        </h2>
    </div>

    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Message details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">To</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $message->tenant->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">From</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $message->sender->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $message->subject }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Body</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ nl2br(e($message->body)) }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sent</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $message->created_at->format('m/d/Y H:i') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Read</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($message->isUnread())
                            <span class="text-rose-600">Not yet read</span>
                        @else
                            <span class="text-emerald-600">Read on {{ $message->read_at->format('m/d/Y H:i') }}</span>
                        @endif
                    </dd>
                </dd>
            </dl>
        </div>
    </section>

    @can('delete', $message)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="delete-message-{{ $message->id }}"
            data-confirm-action="{{ route('app.messages.destroy', $message) }}
        >
            <x-icon name="trash" class="size-4" />
            Remove message
        </x-button>
    @endcan
@endsection