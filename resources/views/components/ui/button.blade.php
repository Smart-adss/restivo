@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
])

@php
    $classes = trim('ui-btn ui-btn-'.$variant.' '.($attributes->get('class') ?? ''));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->except('class')->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
