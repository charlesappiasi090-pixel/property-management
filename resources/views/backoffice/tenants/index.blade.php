@extends('layouts.app')

@section('title', 'Tenants')

@section('headerActions')
    @can('create', \App\Models\Tenant::class)
        <x-button :href="route('app.tenants.create')" size="sm">
            <x-icon name="plus" class="size-4" />
            Add a tenant
        </x-button>
    @endcan
@endsection

@section('content')
    @php
        $atTenantLimit = false; // tenants have no plan limit in Phase 3
    @endphp

    <div class="mb-6">
        <h2 class="text-xl font-bold tracking-tight text-slate-900">Tenants</h2>
        <p class="mt-1 text-sm text-slate-600">
            External landlords / rental entities managed by {{ $business->name }}.
        </p>
    </div>

    <section class="ph-card">
        {{-- ------------------------------------------------------------------ --}}
        {{-- Search and filters                                                 --}}
        {{-- ------------------------------------------------------------------ --}}
        <div class="border-b border-slate-200 p-4">
            <form method="GET" action="{{ route('app.tenants.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[14rem] flex-1">
                    <x-text-field
                        name="search"
                        label="Search"
                        :value="$search"
                        placeholder="Name or email"
                        autocomplete="off"
                    />
                </div>

                <x-button type="submit" variant="secondary">Apply</x-button>

                @if ($search)
                    <x-button :href="route('app.tenants.index')" variant="ghost">Clear</x-button>
                @endif
            </form>
        </div>

        @if ($tenants->isEmpty())
            <x-empty-state
                icon="users"
                title="{{ $search ? 'No tenants match' : 'No tenants yet' }}"
                message="{{ $search
                    ? 'Try a different search, or clear the filters to see the whole list.'
                    : 'Add the first tenant you manage. Their leases and activity stay in the audit log.' }}"
            >
                @can('create', \App\Models\Tenant::class)
                    <x-button :href="route('app.tenants.create')" size="sm">Add a tenant</x-button>
                @endcan
            </x-empty-state>
        @else
            <div class="ph-scroll overflow-x-auto">
                <table class="ph-table">
                    <caption class="ph-sr-only">Tenants managed by {{ $business->name }}</caption>

                    <thead>
                        <tr>
                            <th scope="col">Tenant</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Email</th>
                            <th scope="col">Active lease</th>
                            <th scope="col" class="text-right">
                                <span class="ph-sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($tenants as $tenant)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('app.tenants.show', $tenant) }}"
                                        class="font-medium text-slate-900 hover:text-brand-700 hover:underline"
                                    >
                                        {{ $tenant->name }}
                                    </a>
                                </td>

                                <td>
                                    @if ($tenant->contact_name)
                                        {{ $tenant->contact_name }}
                                    @else
                                        <span class="text-slate-400">Not recorded</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap text-slate-500">
                                    @if ($tenant->email)
                                        <a href="mailto:{{ $tenant->email }}" class="hover:text-brand-700 hover:underline">
                                            {{ $tenant->email }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">Not recorded</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($tenant->activeLease)
                                        <x-status-badge
                                            label="Active"
                                            class="bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200"
                                        />
                                    @else
                                        <x-status-badge
                                            label="No active lease"
                                            class="bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200"
                                        />
                                    @endif
                                </td>

                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $tenant)
                                            <x-button
                                                :href="route('app.tenants.edit', $tenant)"
                                                variant="ghost"
                                                size="sm"
                                            >
                                                Edit
                                            </x-button>
                                        @endcan

                                        @can('delete', $tenant)
                                            <x-button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-rose-600 hover:bg-rose-50"
                                                data-confirm="remove-tenant-{{ $tenant->id }}"
                                                data-confirm-action="{{ route('app.tenants.destroy', $tenant) }}"
                                            >
                                                <span class="ph-sr-only">Remove tenant {{ $tenant->id }}</span>
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

            @if ($tenants->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $tenants->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection