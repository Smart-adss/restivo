@props([
    'title',
    'body' => null,
])

<div {{ $attributes->class('mx-auto mt-8 max-w-lg border border-dashed border-line bg-surface px-6 py-12 text-center') }} style="border-radius: var(--radius-lg)">
    <h2 class="text-xl font-semibold tracking-tight">{{ $title }}</h2>
    @if ($body)
        <p class="mt-2 text-sm text-ink/70">{{ $body }}</p>
    @endif
    @isset($action)
        <div class="mt-6 flex justify-center">
            {{ $action }}
        </div>
    @endisset
</div>
