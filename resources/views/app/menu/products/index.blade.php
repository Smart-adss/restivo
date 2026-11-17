@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.products') }}</h1>
        <a href="{{ route('app.menu.products.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_product') }}</a>
    </div>
    <ul class="mt-6 grid gap-3 md:grid-cols-2">
        @forelse ($products as $product)
            <li>
                <a href="{{ route('app.menu.products.show', $product->public_id) }}" class="block rounded-lg border border-line bg-white px-4 py-4">
                    <p class="font-semibold">{{ $product->name() }}</p>
                    <p class="mt-1 text-sm text-ink/70">{{ $product->category?->name() }} · {{ $product->base_price }}</p>
                </a>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.no_products') }}</li>
        @endforelse
    </ul>
@endsection
