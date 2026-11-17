@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.categories') }}</h1>
        <a href="{{ route('app.menu.categories.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_category') }}</a>
    </div>
    <ul class="mt-6 space-y-3">
        @forelse ($categories as $category)
            <li class="rounded-lg border border-line bg-white px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $category->name() }}</p>
                        <p class="mt-1 text-sm text-ink/70">{{ __('app.sort_order') }}: {{ $category->sort_order }}</p>
                    </div>
                    <a href="{{ route('app.menu.categories.edit', $category->public_id) }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.edit') }}</a>
                </div>
                @if ($category->children->isNotEmpty())
                    <ul class="mt-3 space-y-2 border-s border-line ps-4">
                        @foreach ($category->children as $child)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-sm">{{ $child->name() }}</span>
                                <a href="{{ route('app.menu.categories.edit', $child->public_id) }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.edit') }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.no_categories') }}</li>
        @endforelse
    </ul>
@endsection
