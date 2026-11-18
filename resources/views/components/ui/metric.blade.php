@props([
    'label',
    'value',
])

<div {{ $attributes->class('ui-panel px-4 py-4') }}>
    <dt class="text-sm text-ink/65">{{ $label }}</dt>
    <dd class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">{{ $value }}</dd>
</div>
