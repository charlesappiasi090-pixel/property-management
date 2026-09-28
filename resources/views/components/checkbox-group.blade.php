@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'hint' => null,
    'colspan' => 6,
])

@php
    $current = collect(old($name, $value) ?? [])->map(fn ($v) => (string) $v);
@endphp

<fieldset {{ $attributes->only('class')->merge(['class' => 'sm:col-span-'.$colspan]) }}>
    <legend class="ph-label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
            <span class="ph-sr-only">(required)</span>
        @endif
    </legend>

    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($options as $key => $option)
            @php
                $optValue = is_array($option) ? ($option['value'] ?? $key) : $key;
                $optLabel = is_array($option) ? ($option['label'] ?? $optValue) : $option;
                $boxId = $name.'-'.$optValue;
            @endphp
            <label for="{{ $boxId }}"
                class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5
                       transition-colors hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                <input
                    id="{{ $boxId }}"
                    type="checkbox"
                    name="{{ $name }}[]"
                    value="{{ $optValue }}"
                    @checked($current->contains((string) $optValue))
                    @if ($required) required @endif
                    @if ($errors->has($name)) aria-invalid="true" @endif
                    class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                >
                <span class="text-sm text-slate-700">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if ($hint)
        <p class="ph-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="ph-field-error" role="alert">{{ $message }}</p>
    @enderror
</fieldset>
