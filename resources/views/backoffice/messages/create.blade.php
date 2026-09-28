@extends('layouts.app')

@section('title', 'New message')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <x-button :href="route('app.messages.index')" variant="ghost" size="sm" class="mb-3 -ml-3">
                <x-icon name="chevron-right" class="size-4 rotate-180" />
                Back to inbox
            </x-button>

            <h2 class="text-xl font-bold tracking-tight text-slate-900">
                New message
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Send a message to a tenant.
            </p>
        </div>

        <form method="POST" action="{{ route('app.messages.store') }}" class="space-y-6">
            @csrf

            <x-flash-messages />

            <x-select-field
                name="tenant_id"
                label="Recipient tenant"
                :options=$tenantOptions
                required
            />

            <x-text-field
                name="subject"
                type="text"
                label="Subject"
                placeholder="e.g. Rent increase notice"
                required
                autocomplete="off"
            />

            <x-textarea-field
                name="body"
                type="text"
                label="Message body"
                placeholder="Type your message here..."
                rows="6"
                required
                autocomplete="off"
            />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-button :href="route('app.messages.index')" variant="secondary">Cancel</x-button>

                <x-button type="submit" loading-text="Sending...">
                    <x-icon name="send" class="size-4" />
                    Send message
                </x-button>
            </div>
        </form>
    </div>
@endsection