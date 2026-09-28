@props([
    'name',
    'label',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'rows' => 4,
    'maxlength' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full']) }}>
    <label for="{{ $id }}" class="ph-label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
            <span class="ph-sr-only">(required)</span>
        @endif
    </label>

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($hint) aria-describedby="{{ $id }}-hint" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        class="ph-textarea"
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p id="{{ $id }}-hint" class="ph-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="ph-field-error" role="alert">
            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path>
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
