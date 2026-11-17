@props([
    'variant' => 'success',
])

<p {{ $attributes->class(['ui-alert', 'ui-alert-'.$variant]) }}>
    {{ $slot }}
</p>
