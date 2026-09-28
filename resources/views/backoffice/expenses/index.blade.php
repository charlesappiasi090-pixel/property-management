@extends('layouts.app')

@section('title', 'Expenses')

@section('headerActions')
    @can('create', \App\Models\Expense::class)
        <x-button :href="route('app.expenses.create', $property)" size="sm">
            <x-icon name="plus" class="size-4" />
            Add an expense
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atExpenseLimit = false; // expenses have no plan limit in Phase 5
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Expenses</h2>
        <p class="mt-1 text-sm text-slate-600">
            Expenses for {{ $property->name }}.
        </p>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h3 class="font-semibold">Total spent</h3>
            <p class="mt-1 text-sm text-slate-600">
                {{ $expenses->isNotEmpty() ? number_format(array_sum($expenses->pluck('amount')->toArray()), 2) : '0.00' }}
            </p>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Search and filters                                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="border-b border-slate-200 p-4">
        <form method="GET" action="{{ route('app.expenses.index', $property) }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <x-text-field
                    name="search"
                    label="Search"
                    :value="$search"
                    placeholder="Category, description, or tenant"
                    autocomplete="off"
                />
            </div>

            <div class="min-w-[12rem]">
                <x-select-field
                    name="sort"
                    label="Sort by"
                    :selected="request()->string('sort')->toString() ?: 'expense_date'"
                    :options="[
                        'expense_date' => 'Date',
                        'category' => 'Category',
                        'status' => 'Status',
                        'tenant' => 'Tenant',
                    ]"
                />
            </div>

            <div class="min-w-[10rem]">
                <x-select-field
                    name="direction"
                    label="Order"
                    :selected="request()->string('direction')->toString() ?: 'desc'"
                    :options="['desc' => 'Newest first', 'asc' => 'Oldest first']"
                />
            </div>

            <x-button type="submit" variant="secondary">Apply</x-button>

            @if ($search)
                <x-button :href="route('app.expenses.index', $property)" variant="ghost">Clear</x-button>
            @endif
        </form>
    </div>

    @if ($expenses->isEmpty())
        <x-empty-state
            icon="receipt"
            title="{{ $search ? 'No expenses match' : 'No expenses yet' }}"
            message="{{ $search
                ? 'Try a different search, or clear the filters to see the whole portfolio.'
                : 'Add the first expense for this property. Its category, amount and date will appear here.' }}"
        >
            @can('create', \App\Models\Expense::class)
                <x-button :href="route('app.expenses.create', $property)" size="sm">Add an expense</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="ph-scroll overflow-x-auto">
            <table class="ph-table">
                <caption class="ph-sr-only">Expenses for {{ $property->name }}</caption>

                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">
                            <span class="ph-sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($expenses as $expense)
                        <tr class="{{ $expense->status === 'pending' ? 'bg-slate-50/60' : '' }}">
                            <td>
                                <a
                                    href="#"
                                    class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                >
                                    {{ $expense->categoryLabel() }}
                                </a>
                            </td>

                            <td class="tabular-nums text-slate-600">
                                {{ $expense->formattedAmount }}
                                <span class="text-xs text-slate-400">/ month</span>
                            </td>

                            <td class="whitespace-nowrap text-slate-500">
                                {{ $expense->expense_date->format('m/d/Y') }}
                            </td>

                            <td>
                                <x-status-badge
                                    label=$expense->statusLabel()
                                    class="bg-{$expense->status === 'pending' ? 'slate' : ($expense->status === 'approved' ? 'emerald' : ($expense->status === 'reimbursed' ? 'amber' : 'rose'))}-
                                        50 text-{$expense->status === 'pending' ? 'slate-600' : ($expense->status === 'approved' ? 'emerald-700' : ($expense->status === 'reimbursed' ? 'amber-600' : 'rose-600'))} ring-1 ring-inset ring-{$expense->status === 'pending' ? 'slate-200' : ($expense->status === 'approved' ? 'emerald-200' : ($expense->status === 'reimbursed' ? 'amber-200' : 'rose-200'))}
                                />
                            </td>

                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $expense)
                                        <x-button
                                            :href="route('app.expenses.edit', [$property, $expense])"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Edit
                                        </x-button>
                                    @endcan

                                    @can('delete', $expense)
                                        <x-button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="remove-expense-{{ $expense->id }}"
                                            data-confirm-action="{{ route('app.expenses.destroy', [$property, $expense]) }}"
                                        >
                                            <span class="ph-sr-only">Remove expense {{ $expense->id }}</span>
                                            <x-icon name="trash" class="size-4" />
                                        </x-button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($expenses->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $expenses->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection