@props([
    'type' => 'text',
])

<input
    type="{{ $type }}"
    {{ $attributes->class('ui-field') }}
>
