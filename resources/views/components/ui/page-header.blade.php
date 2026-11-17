@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-ink/65">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
