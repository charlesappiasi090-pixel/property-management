@extends('layouts.app')

@section('title', 'Maintenance')

@section('headerActions')
    @can('create', \App\Models\MaintenanceRequest::class)
        <x-button :href="route('app.maintenance.create', $property)" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a maintenance request
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atRequestLimit = false; // maintenance requests have no plan limit in Phase 6
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Maintenance Requests</h2>
        <p class="mt-1 text-sm text-slate-600">
            Open maintenance requests for {{ $property->name }}.
        </p>
    </div>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h3 class="font-semibold">Open requests: {{ MaintenanceRequest::open()->count() }}</h3>
            <p class="mt-1 text-sm text-slate-600">
               Requests still awaiting attention.
            </p>
        </div>

        @if ($atRequestLimit)
            <div class="ph-alert ph-alert-warning mb-6" role="status">
                <x-icon name="exclamation-triangle" class="size-5 shrink-0" />
                <div>
                    <p class="text-sm font-semibold">Your portfolio has no room for another maintenance request.</p>
                    <p class="mt-1 text-sm">
                        Remove a request you no longer manage, or
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
        <form method="GET" action="{{ route('app.maintenance.index', $property) }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <x-text-field
                    name="search"
                    label="Search"
                    :value="$search"
                    placeholder="Category, priority, or tenant"
                    autocomplete="off"
                />
            </div>

            <div class="min-w-[12rem]">
                <x-select-field
                    name="sort"
                    label="Sort by"
                    :selected="request()->string('sort')->toString() ?: 'requested_at'"
                    :options="[
                        'requested_at' => 'Date requested',
                        'priority' => 'Priority',
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
                <x-button :href="route('app.maintenance.index', $property)" variant="ghost">Clear</x-button>
            @endif
        </form>
    </div>

    @if ($requests->isEmpty())
        <x-empty-state
            icon="tools"
            title="{{ $search ? 'No requests match' : 'No maintenance requests yet' }}"
            message="{{ $search
                ? 'Try a different search, or clear the filters to see the whole list.'
                : 'Add the first maintenance request for this property. Its category, priority and description will appear here.' }}"
        >
            @can('create', \App\Models\MaintenanceRequest::class)
                <x-button :href="route('app.maintenance.create', $property)" size="sm">Add a maintenance request</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="ph-scroll overflow-x-auto">
            <table class="ph-table">
                <caption class="ph-sr-only">Maintenance requests for {{ $property->name }}</caption>

                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Reporter</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-right">
                            <span class="ph-sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($requests as $request)
                        <tr class="{{ $request->status === 'open' ? '' : 'bg-slate-50/60' }}">
                            <td>
                                <a
                                    href="#"
                                    class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                >
                                    {{ $request->categoryLabel() }}
                                </a>
                            </td>

                            <td>
                                <x-status-badge
                                    label=$request->priorityLabel()
                                    class="bg-{$request->priority === 'routine' ? 'slate' : ($request->priority === 'urgent' ? 'amber' : 'rose')}-
                                        50 text-{$request->priority === 'routine' ? 'slate-600' : ($request->priority === 'urgent' ? 'amber-600' : 'rose-600')} ring-1 ring-inset ring-{$request->priority === 'routine' ? 'slate-200' : ($request->priority === 'urgent' ? 'amber-200' : 'rose-200')}
                                />
                            </td>

                            <td>
                                @if ($request->reporter_name)
                                    {{ $request->reporter_name }}
                                @else
                                    <span class="text-slate-400">Not recorded</span>
                                @endif
                            </td>

                            <td>
                                <x-status-badge
                                    label=$request->statusLabel()
                                    class="bg-{$request->status === 'open' ? 'emerald' : ($request->status === 'in_progress' ? 'amber' : ($request->status === 'completed' ? 'emerald' : 'rose'))}-
                                        50 text-{$request->status === 'open' ? 'emerald-600' : ($request->status === 'in_progress' ? 'amber-600' : ($request->status === 'completed' ? 'emerald-600' : 'rose-600'))} ring-1 ring-inset ring-{$request->status === 'open' ? 'emerald-200' : ($request->status === 'in_progress' ? 'amber-200' : ($request->status === 'completed' ? 'emerald-200' : 'rose-200'))}
                                />
                            </td>

                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $request)
                                        <x-button
                                            :href="route('app.maintenance.edit', [$property, $request])"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Edit
                                        </x-button>
                                    @endcan

                                    @can('delete', $request)
                                        <x-button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="remove-request-{{ $request->id }}"
                                            data-confirm-action="{{ route('app.maintenance.destroy', [$property, $request]) }}"
                                        >
                                            <span class="ph-sr-only">Remove request {{ $request->id }}</span>
                                            <x-icon name="trash" class="size-4" />
                                        </x-button>
                                    @endcan
                                </td>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $requests->links() }}
            </div>
        @endif
    @endif
@endsection