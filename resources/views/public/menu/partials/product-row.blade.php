@php
    $name = app()->getLocale() === 'ar' ? $product['name_ar'] : $product['name_en'];
    $desc = app()->getLocale() === 'ar' ? ($product['description_ar'] ?? null) : ($product['description_en'] ?? null);
@endphp
<li class="public-product" data-product-id="{{ $product['public_id'] }}" id="product-{{ $product['public_id'] }}">
    <div class="public-product-media">
        @if (! empty($product['image_url']))
            <img src="{{ $product['image_url'] }}" alt="{{ $name }}" loading="lazy" width="88" height="88">
        @endif
    </div>
    <div class="flex min-w-0 flex-col justify-between">
        <div>
            <p class="font-semibold leading-snug">{{ $name }}</p>
            @if ($desc)
                <p class="mt-1 line-clamp-2 text-sm text-ink/65">{{ $desc }}</p>
            @endif
        </div>
        <div class="mt-3 flex items-center justify-between gap-2">
            <p class="font-semibold tabular-nums">{{ $product['price'] }}</p>
            @if (! empty($ordering_enabled))
                <button type="button" class="ui-btn ui-btn-primary px-3 text-sm" data-add-product="{{ $product['public_id'] }}">
                    {{ __('app.add_to_order') }}
                </button>
            @endif
        </div>
    </div>
</li>
