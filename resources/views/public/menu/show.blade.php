<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $branch->name() }} · {{ $tenant->displayName() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --color-brand: {{ $theme['brand'] ?? '#145c40' }};
                --color-canvas: {{ $theme['canvas'] ?? '#e7eeea' }};
                --color-ink: {{ $theme['ink'] ?? '#12201b' }};
                --color-accent: {{ $theme['accent'] ?? '#f2c14e' }};
                --color-line: {{ $theme['line'] ?? '#d5e0da' }};
                --color-brand-ink: {{ $theme['brand-ink'] ?? '#f3faf6' }};
            }
        </style>
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <header class="border-b border-line bg-white">
            <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-3 px-4 py-4">
                <div>
                    <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $branch->name() }}</h1>
                </div>
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                    <button type="submit" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ app()->getLocale() === 'ar' ? 'EN' : 'عربي' }}</button>
                </form>
            </div>
        </header>

        <main class="mx-auto w-full max-w-xl px-4 py-6">
            @if ($closed_today || ! $is_open)
                <p class="mb-6 rounded-lg border border-s-4 border-accent bg-white px-4 py-3 text-sm">
                    {{ __('app.branch_closed_now') }}
                    @if (! $closed_today && $opens_at && $closes_at)
                        · {{ substr($opens_at, 0, 5) }} – {{ substr($closes_at, 0, 5) }}
                    @endif
                </p>
            @endif

            @forelse ($categories as $category)
                <section class="mb-8">
                    <h2 class="mb-3 text-lg font-semibold">{{ app()->getLocale() === 'ar' ? $category['name_ar'] : $category['name_en'] }}</h2>
                    <ul class="space-y-3">
                        @foreach ($category['products'] as $product)
                            <li class="border-b border-line pb-3" data-product-id="{{ $product['public_id'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ app()->getLocale() === 'ar' ? $product['name_ar'] : $product['name_en'] }}</p>
                                        @php($desc = app()->getLocale() === 'ar' ? $product['description_ar'] : $product['description_en'])
                                        @if ($desc)
                                            <p class="mt-1 line-clamp-2 text-sm text-ink/70">{{ $desc }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-end">
                                        <p class="font-semibold tabular-nums">{{ $product['price'] }}</p>
                                        @if (! empty($ordering_enabled))
                                            <button type="button" class="mt-2 min-h-11 rounded-lg bg-brand px-3 text-sm text-brand-ink" data-add-product="{{ $product['public_id'] }}">{{ __('app.add_to_order') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @foreach ($category['children'] as $child)
                        <div class="mt-5">
                            <h3 class="mb-2 text-base font-semibold">{{ app()->getLocale() === 'ar' ? $child['name_ar'] : $child['name_en'] }}</h3>
                            <ul class="space-y-3">
                                @foreach ($child['products'] as $product)
                                    <li class="border-b border-line pb-3" data-product-id="{{ $product['public_id'] }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-semibold">{{ app()->getLocale() === 'ar' ? $product['name_ar'] : $product['name_en'] }}</p>
                                            </div>
                                            <div class="shrink-0 text-end">
                                                <p class="font-semibold tabular-nums">{{ $product['price'] }}</p>
                                                @if (! empty($ordering_enabled))
                                                    <button type="button" class="mt-2 min-h-11 rounded-lg bg-brand px-3 text-sm text-brand-ink" data-add-product="{{ $product['public_id'] }}">{{ __('app.add_to_order') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </section>
            @empty
                <p class="text-sm text-ink/70">{{ __('app.public_menu_empty') }}</p>
            @endforelse
        </main>

        @if (! empty($ordering_enabled))
            <aside id="guest-order" class="fixed inset-x-0 bottom-0 border-t border-line bg-white">
                <div class="mx-auto w-full max-w-xl px-4 py-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="font-semibold">{{ __('app.your_order') }}</p>
                        <p id="cart-count" class="text-sm text-ink/60">0</p>
                    </div>
                    <ul id="cart-lines" class="mb-3 max-h-40 space-y-2 overflow-y-auto text-sm"></ul>
                    <div class="mb-3 space-y-1 text-sm">
                        <div class="flex justify-between"><span>{{ __('app.subtotal') }}</span><span id="quote-subtotal" class="tabular-nums">—</span></div>
                        <div class="flex justify-between"><span>{{ __('app.tax_total') }}</span><span id="quote-tax" class="tabular-nums">—</span></div>
                        <div class="flex justify-between"><span>{{ __('app.delivery_fee') }}</span><span id="quote-delivery" class="tabular-nums">—</span></div>
                        <div class="flex justify-between font-semibold"><span>{{ __('app.total') }}</span><span id="quote-total" class="tabular-nums">—</span></div>
                        <p id="quote-note" class="text-xs text-ink/60">{{ __('app.server_priced_summary') }}</p>
                        <p id="order-error" class="hidden text-sm text-danger"></p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="text-sm">{{ __('app.order_type') }}
                            <select id="order-type" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                                <option value="pickup">{{ __('app.pickup') }}</option>
                                <option value="delivery">{{ __('app.delivery') }}</option>
                            </select>
                        </label>
                        <label class="text-sm">{{ __('app.delivery_zone') }}
                            <select id="order-zone" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3" disabled>
                                <option value="">{{ __('app.choose_zone') }}</option>
                                @foreach ($delivery_zones as $zone)
                                    <option value="{{ $zone['public_id'] }}">{{ app()->getLocale() === 'ar' ? $zone['name_ar'] : $zone['name_en'] }} ({{ $zone['fee'] }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm sm:col-span-2">{{ __('app.customer_name') }}
                            <input id="customer-name" type="text" maxlength="120" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                        </label>
                        <label class="text-sm sm:col-span-2">{{ __('app.customer_phone') }}
                            <input id="customer-phone" type="tel" placeholder="+2010..." class="mt-1 w-full min-h-11 rounded-lg border border-line px-3" dir="ltr">
                        </label>
                        <label id="address-wrap" class="hidden text-sm sm:col-span-2">{{ __('app.address_line') }}
                            <input id="address-line" type="text" maxlength="255" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                        </label>
                        <label class="text-sm sm:col-span-2">{{ __('app.coupon_code') }}
                            <input id="coupon-code" type="text" maxlength="40" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                        </label>
                    </div>
                    <button id="place-order" type="button" class="mt-3 w-full min-h-11 rounded-lg bg-brand font-semibold text-brand-ink disabled:opacity-50" disabled>{{ __('app.place_order') }}</button>
                </div>
            </aside>

            <script>
                (() => {
                    const catalog = @json($catalog);
                    const branchId = @json($branch->public_id);
                    const slug = @json($tenant->slug);
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
    </body>
</html>
