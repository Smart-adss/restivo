@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.products')" :description="__('app.products_catalog_hint')">
        <x-slot:actions>
            <x-ui.button :href="route('app.menu.products.create')" variant="primary">
                {{ __('app.create_product') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($products->isEmpty())
        <x-ui.empty-state :title="__('app.no_products')" :body="__('app.products_empty_hint')">
            <x-slot:action>
                <x-ui.button :href="route('app.menu.products.create')" variant="primary">
                    {{ __('app.create_product') }}
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $product)
                @php($primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first())
                <li>
                    <a href="{{ route('app.menu.products.show', $product->public_id) }}" class="ui-panel flex h-full flex-col overflow-hidden transition hover:border-brand">
                        <div class="flex min-h-28 items-end border-b border-line bg-[linear-gradient(160deg,color-mix(in_srgb,var(--color-canvas)_70%,white),var(--color-surface))] px-4 py-3">
                            @if ($primary)
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink/55">{{ __('app.has_image') }}</p>
                            @else
                                <p class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('app.no_images') }}</p>
                            @endif
                        </div>
                        <div class="px-4 py-4">
                            <p class="font-semibold">{{ $product->name() }}</p>
                            <p class="mt-1 text-sm text-ink/65">
                                {{ $product->category?->name() }}
                                · <span class="tabular-nums font-semibold text-ink">{{ $product->base_price }}</span>
                            </p>
                            @unless ($product->is_active)
                                <p class="mt-2 text-xs font-semibold text-danger">{{ __('app.inactive') }}</p>
                            @endunless
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
