@props([
    'name',
    'label',
    'checked' => false,
    'hint' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id') ?? $name;
    /*
     * `old()` wins over the record's value, so a checkbox that failed
     * validation comes back ticked exactly as the user left it. The
     * `hasOld()` guard is what makes that true for the UNCHECKED case: an
     * unticked box is not submitted at all, so its key is missing from the old
     * input and `old()` returns null. Reading that null as false is right for
     * this component only because absence means "off" — the server-side
     * `$this->boolean()` does the same thing.
     */
    $isChecked = $request->hasOld($name) ? (bool) old($name) : (bool) $checked;
@endphp

<label
    for="{{ $id }}"
    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition-colors
           hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50
           {{ $isChecked ? 'border-brand-500 bg-brand-50' : '' }}
           {{ $disabled ? 'cursor-not-allowed opacity-60' : '' }}"
>
    <input
        id="{{ $id }}"
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked($isChecked)
        @disabled($disabled)
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
    >

    <span class="min-w-0">
        <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>

        @if ($hint)
            <span id="{{ $id }}-hint" class="ph-hint block">{{ $hint }}</span>
        @endif
    </span>
</label>

@error($name)
    <p id="{{ $id }}-error" class="ph-field-error" role="alert">
        <x-icon name="exclamation-triangle" class="size-4 shrink-0" />
        <span>{{ $message }}</span>
    </p>
@enderror
