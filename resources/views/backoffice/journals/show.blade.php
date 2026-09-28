@extends('layouts.app')

@section('title', 'Journal entry – {{ $entry->description }}')

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.dashboard')" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to dashboard
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Journal entry – {{ $entry->description }}
        </h2>
    </div>

    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Entry details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $entry->description }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <span class="text-2xl font-mono {{ $entry->transaction_type === 'debit' ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $entry->transaction_type === 'debit' ? '-' : '+' }} {{ number_format($entry->amount, 2) }}
                        </span>
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($entry->transaction_type === 'debit')
                            <x-status-badge
                                label="Debit"
                                class="bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200"
                            />
                        @else
                            <x-status-badge
                                label="Credit"
                                class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                            />
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $entry->created_at->format('m/d/Y H:i') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($entry->reference_type)
                            <code class="text-slate-400 break-all">
                                {{ $entry->reference_type }} #{{ $entry->reference_id }}
                            </code>
                        @else
                            <span class="text-slate-400">None</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    @can('update', $entry)
        <x-button :href="route('app.journals.edit', $entry)" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $entry)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="Are you sure you want to delete this journal entry?"
            data-confirm-action="{{ route('app.journals.destroy', $entry) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove
        </x-button>
    @endcan
@endsection