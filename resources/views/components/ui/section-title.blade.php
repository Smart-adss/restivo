@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('mb-3') }}>
    <h2 class="text-lg font-semibold tracking-tight">{{ $title }}</h2>
    @if ($description)
        <p class="mt-1 text-sm text-ink/65">{{ $description }}</p>
    @endif
</div>
