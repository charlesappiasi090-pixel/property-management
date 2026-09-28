@extends('layouts.app')

@section('title', 'Maintenance request – '.$request->categoryLabel())

@section('content')
    <div class="mb-6">
        <x-button :href="route('app.properties.show', $property)" variant="ghost" size="sm" class="mb-3 -ml-3">
            <x-icon name="chevron-right" class="size-4 rotate-180" />
            Back to {{ $property->name }}
        </x-button>

        <h2 class="text-xl font-bold tracking-tight text-slate-900">
            Maintenance request – {{ $request->categoryLabel() }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            Property: {{ $property->name }}
        </p>
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Details                                                                --}}
    {{-- ------------------------------------------------------------------ --}}
    <section class="ph-card">
        <div class="ph-card-header">
            <h3 class="ph-card-title">Request details</h3>
        </div>

        <div class="ph-card-body">
            <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Category</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $request->categoryLabel() }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <x-status-badge
                            label=$request->priorityLabel()
                            class="bg-{$request->priority === 'routine' ? 'slate' : ($request->priority === 'urgent' ? 'amber' : 'rose')}-
                                                50 text-{$request->priority === 'routine' ? 'slate-600' : ($request->priority === 'urgent' ? 'amber-600' : 'rose-600')} ring-1 ring-inset ring-{$request->priority === 'routine' ? 'slate-200' : ($request->priority === 'urgent' ? 'amber-200' : 'rose-200')}
                        />
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        <x-status-badge
                            label=$request->statusLabel()
                            class="bg-{$request->status === 'open' ? 'emerald' : ($request->status === 'in_progress' ? 'amber' : ($request->status === 'completed' ? 'emerald' : 'rose'))}-
                                50 text-{$request->status === 'open' ? 'emerald-600' : ($request->status === 'in_progress' ? 'amber-600' : ($request->status === 'completed' ? 'emerald-600' : 'rose-600'))} ring-1 ring-inset ring-{$request->status === 'open' ? 'emerald-200' : ($request->status === 'in_progress' ? 'amber-200' : ($request->status === 'completed' ? 'emerald-200' : 'rose-200'))}
                        />
                    </dd>
                </dd>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reporter</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($request->reporter_name)
                            {{ $request->reporter_name }}
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>

                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($request->reporter_email)
                            <a href="mailto:{{ $request->reporter_email }}" class="text-slate-600 hover:hover-underline">
                                {{ $request->reporter_email }}
                            </a>
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if ($request->description)
                            {{ $request->description }}
                        @else
                            <span class="text-slate-400">Not recorded</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested at</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        {{ $request->requested_at->format('m/d/Y') }}
                    </dd>
                </div>

                @if ($request->resolved_at)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resolved at</dt>
                        <dd class="mt-1 text-sm text-slate-900">
                            {{ $request->resolved_at->format('m/d/Y') }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    {{-- ------------------------------------------------------------------ --}}
    {{-- Buttons                                                               --}}
    {{-- ------------------------------------------------------------------ --}}
    @can('update', $request)
        <x-button :href="route('app.maintenance.edit', [$property, $request])" variant="secondary" size="sm">
            <x-icon name="pencil-square" class="size-4" />
            Edit
        </x-button>
    @endcan

    @can('delete', $request)
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            class="mt-4 text-rose-600 hover:bg-rose-50"
            data-confirm="remove-request-{{ $request->id }}"
            data-confirm-action="{{ route('app.maintenance.destroy', [$property, $request]) }}"
        >
            <x-icon name="trash" class="size-4" />
            Remove request
        </x-button>
    @endcan
@endsection