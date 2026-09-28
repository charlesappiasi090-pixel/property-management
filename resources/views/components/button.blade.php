@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
    'href' => null,
    'name' => null,
    'value' => null,
    'disabled' => false,
    'loading' => false,
    'loadingText' => 'Working...',
])

@php
    /*
     * The size modifier is mapped rather than interpolated. Building the class
     * as 'ph-btn-'.$size silently ignored anything that was not `sm`: passing
     * size="lg" produced no class at all, so the caller got a medium button
     * and no error. An unknown size now falls back to `md` on purpose.
     */
    $sizes = ['sm' => 'ph-btn-sm', 'lg' => 'ph-btn-lg', 'md' => ''];
    $classes = 'ph-btn-'.$variant.($size === 'md' ? '' : ' '.($sizes[$size] ?? ''));
    $classes = trim($classes);
    $tag = $href ? 'a' : 'button';
@endphp

@if ($href)
    {{--
        An anchor, so no `type`, `disabled`, or `loading` are emitted. Those
        attributes are meaningless on a link: `disabled` is ignored entirely, and
        `type="submit"` on an <a> would be read as the URL's media type. A
        caller who wants a disabled-looking link should style it and omit
        `href`, which makes this branch render a real <button disabled>.
    --}}
    <a {{ $attributes->merge(['class' => $classes, 'href' => $href]) }}>
        {{ $slot }}
    </a>
@else
    <button
        {{ $attributes->merge(['class' => $classes]) }}
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($value !== null) value="{{ $value }}" @endif
        @disabled($disabled || $loading)
        @if ($loading) aria-busy="true" @endif
    >
        @if ($loading)
            {{--
                Inline SVG spinner, not a CSS-only pseudo-element, so the
                state is a real node in the accessibility tree.

                `aria-live="polite"` announces the replacement text as it
                appears. `aria-busy` on the button is what tells assistive tech
                the control is mid-operation; without it a screen reader user
                gets no indication that pressing the button did anything.
            --}}
            <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span aria-live="polite">{{ $loadingText }}</span>
        @else
            {{ $slot }}
        @endif
    </button>
@endif
