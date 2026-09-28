@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'autocomplete' => null,
    'disabled' => false,
    'readonly' => false,
    'prefix' => null,
    'suffix' => null,
    'min' => null,
    'max' => null,
    'step' => null,
    'inputmode' => null,

    /*
     * Messages to render as errors, for forms whose validation writes to a
     * named error bag.
     *
     * `PasswordController::update()` uses `validateWithBag('updatePassword', ...)`
     * so a password error does not appear on the profile form above it. The
     * `@error` directive only inspects the DEFAULT bag, so it cannot see those
     * messages. Passing them in explicitly is what lets this component be
     * reused on a page carrying several forms.
     *
     * The component still renders its own `@error` block when `$name` failed in
     * the default bag; `aria-invalid` and `aria-describedby` follow whichever
     * set is present.
     */
    'errorMessages' => null,
])

@php
    $id = $attributes->get('id') ?? $name;

    $extraErrors = collect($errorMessages)->filter()->values();

    $hasError = $errors->has($name) || $extraErrors->isNotEmpty();

    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ])->filter()->implode(' ');

    // Attributes meant for the WRAPPER (layout) rather than the input.
    // Everything else is forwarded to the <input>, which is what makes
    // `maxlength`, `pattern`, `data-*` and friends actually reach the field
    // instead of being silently dropped onto the wrapper div.
    $wrapperAttributes = $attributes->only('class');
    $inputAttributes = $attributes->except('class');
@endphp

<div {{ $wrapperAttributes->merge(['class' => 'w-full']) }}>
    <label for="{{ $id }}" class="ph-label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
            <span class="ph-sr-only">(required)</span>
        @endif
    </label>

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">
                {{ $prefix }}
            </span>
        @endif

        <input
            {{ $inputAttributes->merge(['id' => $id, 'name' => $name, 'type' => $type]) }}
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($min !== null) min="{{ $min }}" @endif
            @if ($max !== null) max="{{ $max }}" @endif
            @if ($step !== null) step="{{ $step }}" @endif
            @if ($inputmode) inputmode="{{ $inputmode }}" @endif
            @disabled($disabled)
            @readonly($readonly)
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            @class([
                'ph-input',
                'pl-8' => $prefix,
                'pr-12' => $suffix,
            ])
        >

        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-slate-500">
                {{ $suffix }}
            </span>
        @endif
    </div>

    @if ($hint)
        <p id="{{ $id }}-hint" class="ph-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        {{--
            `role="alert"` so a screen reader announces the message when it
            appears. Both the default bag and the passed-in bag render into this
            one block, so a field never shows two stacked error paragraphs.
        --}}
        <p id="{{ $id }}-error" class="ph-field-error" role="alert">
            <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 9a1 1 0 012 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path>
            </svg>
            <span>{{ $extraErrors->isNotEmpty() ? $extraErrors->first() : $errors->first($name) }}</span>
        </p>
    @endif
</div>
