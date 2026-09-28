@extends('layouts.app')

@section('title', 'Payments')

@section('headerActions')
    @can('create', \App\Models\Payment::class)
        <x-button :href="route('app.payments.create')" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a payment
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atPaymentLimit = $quota['limit'] !== null && $quota['used'] >= $quota['limit'];
    @endphp

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Payments</h2>
            <p class="mt-1 text-sm text-slate-600">
                @if ($quota['limit'] === null)
                    Every payment recorded for {{ $business->name }}.
                @else
                    {{ number_format($quota['used']) }} of {{ number_format($quota['limit']) }}
                    {{ \Illuminate\Support\Str::plural('payment', $quota['limit']) }} used.
                @endif
            </p>
        </div>

        @if ($atPaymentLimit)
            <div class="ph-alert ph-alert-warning mb-6" role="status">
                <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold">Your plan has no room for another payment.</p>
                    <p class="mt-1 text-sm">
                        Remove a payment you no longer manage, or
                        <a href="{{ route('app.subscription.plans') }}" class="font-semibold underline">upgrade the plan</a>.
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Search and filters                                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    <div class="border-b border-slate-200 p-4">
        <form method="GET" action="{{ route('app.payments.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <x-text-field
                    name="search"
                    label="Search"
                    :value="$search"
                    placeholder="Tenant name, lease, or amount"
                    autocomplete="off"
                />
            </div>

            <div class="min-w-[12rem]">
                <x-select-field
                    name="sort"
                    label="Sort by"
                    :selected="request()->string('sort')->toString() ?: 'payment_date'"
                    :options="[
                        'payment_date' => 'Payment date',
                        'amount' => 'Amount',
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
                <x-button :href="route('app.payments.index')" variant="ghost">Clear</x-button>
            @endif
        </form>
    </div>

    @if ($payments->isEmpty())
        <x-empty-state
            icon="banknotes"
            title="{{ $search ? 'No payments match' : 'No payments yet' }}"
            message="{{ $search
                ? 'Try a different search, or clear the filters to see the whole portfolio.'
                : 'Add the first payment for this portfolio. It will appear here once a lease is active and a payment is recorded.' }}"
        >
            @can('create', \App\Models\Payment::class)
                <x-button :href="route('app.payments.create')" size="sm">Add a payment</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="ph-scroll overflow-x-auto">
            <table class="ph-table">
                <caption class="ph-sr-only">Payments recorded for {{ $business->name }}</caption>

                <thead>
                    <tr>
                        <th scope="col">Tenant</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">
                            <span class="ph-sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($payments as $payment)
                        <tr class="{{ $payment->status === 'active' ? '' : 'bg-slate-50/60' }}">
                            <td>
                                <a
                                    href="{{ route('app.tenants.show', $payment->lease->tenant) }}"
                                    class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                >
                                    {{ $payment->lease->tenant->displayName() }}
                                </a>
                            </td>

                            <td class="tabular-nums text-slate-600">
                                {{ $payment->formattedAmount }}
                                <span class="text-xs text-slate-400">/ month</span>
                            </td>

                            <td class="whitespace-nowrap text-slate-500">
                                {{ $payment->payment_date->format('m/d/Y') }}
                            </td>

                            <td>
                                <x-status-badge
                                    label=$payment->statusLabel()
                                    class="bg-{$payment->status === 'active' ? 'emerald' : ($payment->status === 'failed' ? 'rose' : 'slate')}-
                                        50 text-{$payment->status === 'active' ? 'emerald-700' : ($payment->status === 'failed' ? 'rose-600' : 'slate-600')} ring-1 ring-inset ring-{$payment->status === 'active' ? 'emerald-200' : ($payment->status === 'failed' ? 'rose-200' : 'slate-200')}
                                />
                            </td>

                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $payment)
                                        <x-button
                                            :href="route('app.payments.edit', $payment)"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Edit
                                        </x-button>
                                    @endcan

                                    @can('delete', $payment)
                                        <x-button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="remove-payment-{{ $payment->id }}"
                                            data-confirm-action="{{ route('app.payments.destroy', $payment) }}"
                                        >
                                            <span class="ph-sr-only">Remove payment {{ $payment->id }}</span>
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

        @if ($payments->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $payments->links() }}
            </div>
        @endif
    @endif
@endsection