@extends('layouts.public')

@section('title', $branch->name().' · '.$tenant->displayName())

@section('theme')
    --color-brand: {{ $theme['brand'] ?? '#145c40' }};
    --color-canvas: {{ $theme['canvas'] ?? '#e7eeea' }};
    --color-ink: {{ $theme['ink'] ?? '#12201b' }};
    --color-accent: {{ $theme['accent'] ?? '#f2c14e' }};
    --color-line: {{ $theme['line'] ?? '#d5e0da' }};
    --color-brand-ink: {{ $theme['brand-ink'] ?? '#f3faf6' }};
@endsection

@section('body')
    <header class="public-hero sticky top-0 z-20">
        <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-3 px-4 py-4">
            <div class="min-w-0">
                <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
                <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $branch->name() }}</h1>
            </div>
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                <x-ui.button type="submit" variant="secondary">{{ app()->getLocale() === 'ar' ? 'EN' : 'عربي' }}</x-ui.button>
            </form>
        </div>

        @if ($categories !== [])
            <nav class="mx-auto w-full max-w-xl px-4 pb-3" aria-label="{{ __('app.categories') }}">
                <div class="public-cat-nav">
                    @foreach ($categories as $category)
                        <a href="#cat-{{ $category['public_id'] }}" class="public-cat-chip">
                            {{ app()->getLocale() === 'ar' ? $category['name_ar'] : $category['name_en'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    </header>

    <main class="mx-auto w-full max-w-xl px-4 py-6 @if (! empty($ordering_enabled)) pb-72 @endif">
        @if ($closed_today || ! $is_open)
            <x-ui.alert variant="warning" class="mb-6">
                {{ __('app.branch_closed_now') }}
                @if (! $closed_today && $opens_at && $closes_at)
                    · {{ substr($opens_at, 0, 5) }} – {{ substr($closes_at, 0, 5) }}
                @endif
            </x-ui.alert>
        @endif

        @forelse ($categories as $category)
            <section class="mb-8 scroll-mt-36" id="cat-{{ $category['public_id'] }}">
                <h2 class="mb-3 text-lg font-semibold tracking-tight">
                    {{ app()->getLocale() === 'ar' ? $category['name_ar'] : $category['name_en'] }}
                </h2>
                <ul class="space-y-3">
                    @foreach ($category['products'] as $product)
                        @include('public.menu.partials.product-row', ['product' => $product, 'ordering_enabled' => $ordering_enabled ?? false])
                    @endforeach
                </ul>

                @foreach ($category['children'] as $child)
                    <div class="mt-6">
                        <h3 class="mb-2 text-base font-semibold">
                            {{ app()->getLocale() === 'ar' ? $child['name_ar'] : $child['name_en'] }}
                        </h3>
                        <ul class="space-y-3">
                            @foreach ($child['products'] as $product)
                                @include('public.menu.partials.product-row', ['product' => $product, 'ordering_enabled' => $ordering_enabled ?? false])
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @empty
            <x-ui.empty-state :title="__('app.public_menu_empty')" />
        @endforelse
    </main>

    @if (! empty($ordering_enabled))
        <aside id="guest-order" class="public-cart fixed inset-x-0 bottom-0 z-30">
            <div class="mx-auto w-full max-w-xl px-4 py-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="font-semibold">{{ __('app.your_order') }}</p>
                    <p id="cart-count" class="rounded-md bg-canvas px-2 py-1 text-sm font-semibold tabular-nums text-ink/70">0</p>
                </div>
                <ul id="cart-lines" class="mb-3 max-h-28 space-y-2 overflow-y-auto text-sm"></ul>
                <div class="mb-3 space-y-1 text-sm">
                    <div class="flex justify-between"><span>{{ __('app.subtotal') }}</span><span id="quote-subtotal" class="tabular-nums">—</span></div>
                    <div class="flex justify-between"><span>{{ __('app.tax_total') }}</span><span id="quote-tax" class="tabular-nums">—</span></div>
                    <div class="flex justify-between"><span>{{ __('app.delivery_fee') }}</span><span id="quote-delivery" class="tabular-nums">—</span></div>
                    <div class="flex justify-between font-semibold"><span>{{ __('app.total') }}</span><span id="quote-total" class="tabular-nums">—</span></div>
                    <p id="quote-note" class="text-xs text-ink/60">{{ __('app.server_priced_summary') }}</p>
                    <p id="order-error" class="hidden text-sm text-danger"></p>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <x-ui.label for="order-type">{{ __('app.order_type') }}</x-ui.label>
                        <x-ui.select id="order-type">
                            <option value="pickup">{{ __('app.pickup') }}</option>
                            <option value="delivery">{{ __('app.delivery') }}</option>
                        </x-ui.select>
                    </div>
                    <div>
                        <x-ui.label for="order-zone">{{ __('app.delivery_zone') }}</x-ui.label>
                        <x-ui.select id="order-zone" disabled>
                            <option value="">{{ __('app.choose_zone') }}</option>
                            @foreach ($delivery_zones as $zone)
                                <option value="{{ $zone['public_id'] }}">{{ app()->getLocale() === 'ar' ? $zone['name_ar'] : $zone['name_en'] }} ({{ $zone['fee'] }})</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="customer-name">{{ __('app.customer_name') }}</x-ui.label>
                        <x-ui.field id="customer-name" type="text" maxlength="120" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="customer-phone">{{ __('app.customer_phone') }}</x-ui.label>
                        <x-ui.field id="customer-phone" type="tel" placeholder="+2010..." dir="ltr" />
                    </div>
                    <div id="address-wrap" class="hidden sm:col-span-2">
                        <x-ui.label for="address-line">{{ __('app.address_line') }}</x-ui.label>
                        <x-ui.field id="address-line" type="text" maxlength="255" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="coupon-code">{{ __('app.coupon_code') }}</x-ui.label>
                        <x-ui.field id="coupon-code" type="text" maxlength="40" />
                    </div>
                </div>
                <button id="place-order" type="button" class="ui-btn ui-btn-primary mt-3 w-full min-h-12" disabled>{{ __('app.place_order') }}</button>
            </div>
        </aside>

        <script>
            (() => {
                const catalog = @json($catalog);
                const branchId = @json($branch->public_id);
                const locale = @json(app()->getLocale());
                const quoteUrl = @json(route('api.public.orders.quote', ['slug' => $tenant->slug]));
                const placeUrl = @json(route('api.public.orders.store', ['slug' => $tenant->slug]));
                const trackBase = @json(url('/'.$tenant->slug.'/orders'));
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const byId = Object.fromEntries(catalog.map((p) => [p.public_id, p]));
                const cart = [];
                let quoteTimer = null;

                const els = {
                    lines: document.getElementById('cart-lines'),
                    count: document.getElementById('cart-count'),
                    subtotal: document.getElementById('quote-subtotal'),
                    tax: document.getElementById('quote-tax'),
                    delivery: document.getElementById('quote-delivery'),
                    total: document.getElementById('quote-total'),
                    error: document.getElementById('order-error'),
                    type: document.getElementById('order-type'),
                    zone: document.getElementById('order-zone'),
                    addressWrap: document.getElementById('address-wrap'),
                    address: document.getElementById('address-line'),
                    name: document.getElementById('customer-name'),
                    phone: document.getElementById('customer-phone'),
                    coupon: document.getElementById('coupon-code'),
                    place: document.getElementById('place-order'),
                };

                function nameOf(row) {
                    return locale === 'ar' ? row.name_ar : row.name_en;
                }

                function renderCart() {
                    els.lines.innerHTML = cart.map((line, index) => {
                        const product = byId[line.product_id];
                        return `<li class="flex justify-between gap-2"><span>${nameOf(product)} × ${line.quantity}</span><button type="button" data-remove="${index}" class="text-danger">×</button></li>`;
                    }).join('');
                    els.count.textContent = String(cart.reduce((n, line) => n + Number(line.quantity), 0));
                    els.place.disabled = cart.length === 0;
                    scheduleQuote();
                }

                function cartPayload() {
                    const payload = {
                        branch_id: branchId,
                        type: els.type.value,
                        customer_name: els.name.value || null,
                        customer_phone: els.phone.value || null,
                        coupon_code: els.coupon.value || null,
                        items: cart,
                    };
                    if (els.type.value === 'delivery') {
                        payload.zone_id = els.zone.value || null;
                        payload.address_line = els.address.value || null;
                    }
                    return payload;
                }

                async function refreshQuote() {
                    if (cart.length === 0) {
                        els.subtotal.textContent = els.tax.textContent = els.delivery.textContent = els.total.textContent = '—';
                        return;
                    }
                    try {
                        const { data } = await window.axios.post(quoteUrl, cartPayload(), {
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        });
                        const q = data.data;
                        els.subtotal.textContent = q.subtotal;
                        els.tax.textContent = q.tax_total;
                        els.delivery.textContent = q.delivery_fee;
                        els.total.textContent = q.total;
                        els.error.classList.add('hidden');
                    } catch (error) {
                        const message = error.response?.data?.message
                            || Object.values(error.response?.data?.errors || {}).flat()[0]
                            || 'Quote failed';
                        els.error.textContent = message;
                        els.error.classList.remove('hidden');
                    }
                }

                function scheduleQuote() {
                    clearTimeout(quoteTimer);
                    quoteTimer = setTimeout(refreshQuote, 250);
                }

                function ulid() {
                    const time = Date.now().toString(16).padStart(12, '0');
                    const rand = Array.from(crypto.getRandomValues(new Uint8Array(10)))
                        .map((b) => b.toString(16).padStart(2, '0')).join('');
                    return (time + rand).slice(0, 26).toUpperCase();
                }

                document.querySelectorAll('[data-add-product]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-add-product');
                        const existing = cart.find((line) => line.product_id === id && (!line.option_ids || line.option_ids.length === 0));
                        if (existing) {
                            existing.quantity = Math.min(99, Number(existing.quantity) + 1);
                        } else {
                            cart.push({ product_id: id, quantity: 1, option_ids: [] });
                        }
                        renderCart();
                    });
                });

                els.lines.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove]');
                    if (!button) return;
                    cart.splice(Number(button.getAttribute('data-remove')), 1);
                    renderCart();
                });

                function syncType() {
                    const delivery = els.type.value === 'delivery';
                    els.zone.disabled = !delivery;
                    els.addressWrap.classList.toggle('hidden', !delivery);
                    scheduleQuote();
                }

                els.type.addEventListener('change', syncType);
                els.zone.addEventListener('change', scheduleQuote);
                els.coupon.addEventListener('change', scheduleQuote);
                els.phone.addEventListener('change', scheduleQuote);

                els.place.addEventListener('click', async () => {
                    els.place.disabled = true;
                    try {
                        const payload = { ...cartPayload(), idempotency_key: ulid() };
                        const { data } = await window.axios.post(placeUrl, payload, {
                            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        });
                        const order = data.data;
                        window.location.href = `${trackBase}/${order.id}?phone=${encodeURIComponent(order.customer_phone)}`;
                    } catch (error) {
                        const message = error.response?.data?.message
                            || Object.values(error.response?.data?.errors || {}).flat()[0]
                            || 'Order failed';
                        els.error.textContent = message;
                        els.error.classList.remove('hidden');
                        els.place.disabled = cart.length === 0;
                    }
                });

                syncType();
                renderCart();
            })();
        </script>
    @endif
@endsection
