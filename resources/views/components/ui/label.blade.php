@props(['for' => null])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->class('ui-label') }}>
    {{ $slot }}
</label>
