@extends('layouts.app')

@section('title', 'Receipts')

@section('headerActions')
    @can('create', \App\Models\Receipt::class)
        <x-button :href="route('app.receipts.create', $payment)" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a receipt
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atReceiptLimit = false; // receipts have no plan limit in Phase 4
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Receipts</h2>
        <p class="mt-1 text-sm text-slate-600">
            Receipts for payment {{ $payment->formattedAmount }} – {{ $payment->lease->tenant->displayName() }}.
        </p>
    </div>

    <section class="ph-card">
        {{-- ------------------------------------------------------------------ --}}
        {{-- Search and filters                                                 --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="border-b border-slate-200 p-4">
            <form method="GET" action="{{ route('app.receipts.index', $payment) }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[14rem] flex-1">
                    <x-text-field
                        name="search"
                        label="Search"
                        :value="$search"
                        placeholder="Receipt number or tenant"
                        autocomplete="off"
                    />
                </div>

                <x-button type="submit" variant="secondary">Apply</x-button>

                @if ($search)
                    <x-button :href="route('app.receipts.index', $payment)" variant="ghost">Clear</x-button>
                @endif
            </form>
        </div>

        @if ($receipts->isEmpty())
            <x-empty-state
                icon="document-text"
                title="{{ $search ? 'No receipts match' : 'No receipts yet' }}"
                message="{{ $search
                    ? 'Try a different search, or clear the filters to see the whole list.'
                    : 'Add the first receipt for this payment. It will appear here once a payment is recorded and a receipt number is assigned.' }}"
            >
                @can('create', \App\Models\Receipt::class)
                    <x-button :href="route('app.receipts.create', $payment)" size="sm">Add a receipt</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">Receipts for payment {{ $payment->formattedAmount }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">Receipt number</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Date</th>
                            <th scope="col">Tenant</th>
                            <th scope="col" class="text-right">
                                <span class="ph-sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($receipts as $receipt)
                            <tr>
                                <td>
                                    <a
                                        href="#"
                                        class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                    >
                                        {{ $receipt->receipt_number }}
                                    </a>
                                </td>

                                <td class="tabular-nums text-slate-600">
                                    {{ $receipt->formattedAmount }}
                                    <span class="text-xs text-slate-400">/ month</span>
                                </td>

                                <td class="whitespace-nowrap text-slate-500">
                                    {{ $receipt->issued_at->format('m/d/Y') }}
                                </td>

                                <td>
                                    {{ $receipt->payment->lease->tenant->displayName() }}
                                </td>

                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $receipt)
                                            <x-button
                                                :href="route('app.receipts.edit', [$payment, $receipt])"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Edit
                                            </x-button>
                                        @endcan

                                        @can('delete', $receipt)
                                            <x-button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-rose-600 hover:bg-rose-50"
                                                data-confirm="remove-receipt-{{ $receipt->id }}"
                                                data-confirm-action="{{ route('app.receipts.destroy', [$payment, $receipt]) }}"
                                            >
                                                <span class="ph-sr-only">Remove receipt {{ $receipt->id }}</span>
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

            @if ($receipts->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $receipts->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection