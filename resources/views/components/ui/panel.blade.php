@props([
    'padding' => true,
])

<div {{ $attributes->class(['ui-panel', 'px-4 py-4' => $padding]) }}>
    {{ $slot }}
</div>
