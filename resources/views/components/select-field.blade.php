@props([
    'name',
    'label',
    'selected' => null,
    'options' => [],
    'required' => false,
    'hint' => null,
    'placeholder' => 'Select an option',
    'disabled' => false,
])

@php
    $id = $attributes->get('id') ?? $name;
    $current = old($name, $selected);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full']) }}>
    <label for="{{ $id }}" class="ph-label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
            <span class="ph-sr-only">(required)</span>
        @endif
    </label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @disabled($disabled)
        @if ($hint) aria-describedby="{{ $id }}-hint" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        class="ph-select"
    >
        @if ($placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif

        {{--
            $options accepts either a flat [value => label] map or a list of
            ['value' => ..., 'label' => ...] pairs, so callers can pass an
            Eloquent collection or a plain array without reshaping it.
        --}}
        @foreach ($options as $key => $option)
            @php
                $value = is_array($option) ? ($option['value'] ?? $key) : $key;
                $text = is_array($option) ? ($option['label'] ?? $value) : $option;
                $disabledOption = is_array($option) && ($option['disabled'] ?? false);
            @endphp
            <option value="{{ $value }}" @selected((string) $current === (string) $value) @disabled($disabledOption)>
                {{ $text }}
            </option>
        @endforeach
    </select>

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
