@props([
    'status' => null,
    'label' => null,
])

@php
    $text = $label ?? (is_object($status) && method_exists($status, 'label') ? $status->label() : (string) $status);
    $classes = is_object($status) && method_exists($status, 'badgeClasses')
        ? $status->badgeClasses()
        : 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-200';
@endphp

<span {{ $attributes->merge(['class' => 'ph-badge '.$classes]) }}>{{ $text }}</span>
