@extends('layouts.app')

@section('title', $expense->categoryLabel().' expense for '.$property->name)

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to {{ $property->name }}
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            {{ $expense->categoryLabel() }} expense
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ $property->name }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Expense details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $expense->categoryLabel() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $expense->formattedAmount }}
                        <span class="text-xs text-slate-400">/ month</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <x-status-badge
                            label=$expense->statusLabel()
                            class="bg-{$expense->status === 'pending' ? 'slate' : ($expense->status === 'approved' ? 'emerald' : ($expense->status === 'reimbursed' ? 'amber' : 'rose'))}-
                                50 text-{$expense->status === 'pending' ? 'slate-600' : ($expense->status === 'approved' ? 'emerald-700' : ($expense->status === 'reimbursed' ? 'amber-600' : 'rose-600'))} ring-1 ring-inset ring-{$expense->status === 'pending' ? 'slate-200' : ($expense->status === 'approved' ? 'emerald-200' : ($expense->status === 'reimbursed' ? 'amber-200' : 'rose-200'))}
                        />
                    </dd>
                </dd>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($expense->description)
                            {{ $expense->description }}
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expense date</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $expense->expense_date->format('m/d/Y') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Receipt</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($expense->receipt_path)
                            <a href="{{$expense->receipt_path}}" target="_blank" class="text-slate-600 underline">
                                View receipt
                            </a>
                        @else
                            <span class="text-slate-400">No receipt recorded</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Property</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <a
                            href="{{ route('app.properties.show', $property) }}"
                            class="hover:text-brand-700 hover:underline"
                        >
                            {{ $property->name }}
                        </a>
                    </dd>
                </dd>

                @if ($expense->tenant)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $expense->tenant->displayName() }}
                        </dd>
                    </dd>
                @endif
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $expense)
        <x-button :href="route('app.expenses.edit', [$property, $expense])" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $expense)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-expense-{{ $expense->id }}"
            data-confirm-action="{{ route('app.expenses.destroy', [$property, $expense]) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove expense
        </x-button>
    @endcan
@endsection