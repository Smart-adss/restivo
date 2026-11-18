@extends('layouts.public')

@section('title', $table->name.' · '.$branch->name())

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
                <p class="text-sm text-ink/60">{{ $tenant->displayName() }} · {{ $branch->name() }}</p>
                <h1 class="truncate text-2xl font-semibold tracking-tight">{{ __('app.table') }} {{ $table->name }}</h1>
            </div>
            <form method="POST" action="{{ route('locale.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                <x-ui.button type="submit" variant="secondary">{{ app()->getLocale() === 'ar' ? 'EN' : 'عربي' }}</x-ui.button>
            </form>
        </div>
    </header>

    <main class="mx-auto w-full max-w-xl px-4 py-6" x-data="tableOrder({
        quoteUrl: @js($quote_url),
        orderUrl: @js($order_url),
        branchId: @js($branch->public_id),
        catalog: @js($catalog ?? []),
    })">
        @if (! ($ordering_enabled ?? false))
            <x-ui.alert variant="warning">{{ __('app.ordering_unavailable') }}</x-ui.alert>
        @else
            <div class="ui-panel mb-6 space-y-3 px-4 py-4">
                <x-ui.field x-model="customerName" type="text" placeholder="{{ __('app.customer_name') }}" />
                <x-ui.field x-model="customerPhone" type="tel" placeholder="+20..." dir="ltr" />
                <p class="text-sm" x-text="message"></p>
                <p class="text-lg font-semibold tabular-nums" x-show="total">{{ __('app.total') }}: <span x-text="total"></span></p>
                <button type="button" class="ui-btn ui-btn-primary w-full" @click="submitOrder()" :disabled="busy">{{ __('app.place_order') }}</button>
            </div>

            <ul class="space-y-3">
                <template x-for="product in catalog" :key="product.public_id">
                    <li class="public-product !grid-cols-[1fr_auto]">
                        <div>
                            <p class="font-semibold" x-text="product.name_ar"></p>
                            <p class="mt-1 text-sm tabular-nums font-semibold" x-text="product.price"></p>
                        </div>
                        <button type="button" class="ui-btn ui-btn-secondary self-center px-3 text-sm" @click="add(product)">{{ __('app.add_to_order') }}</button>
                    </li>
                </template>
            </ul>
        @endif
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tableOrder', (config) => ({
                quoteUrl: config.quoteUrl,
                orderUrl: config.orderUrl,
                branchId: config.branchId,
                catalog: config.catalog,
                items: [],
                customerName: '',
                customerPhone: '',
                total: '',
                message: '',
                busy: false,
                key: null,
                add(product) {
                    this.items.push({ product_id: product.public_id, quantity: 1, option_ids: [] });
                    this.quote();
                },
                async quote() {
                    if (! this.items.length) {
                        this.total = '';
                        return;
                    }
                    const response = await fetch(this.quoteUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ branch_id: this.branchId, items: this.items }),
                    });
                    const payload = await response.json();
                    if (response.ok) {
                        this.total = payload.data.total;
                        this.message = '';
                    } else {
                        this.message = payload.message || 'Error';
                    }
                },
                async submitOrder() {
                    if (this.busy || ! this.items.length) return;
                    this.busy = true;
                    this.key = this.key || crypto.randomUUID().replace(/-/g, '').slice(0, 26).toUpperCase();
                    const response = await fetch(this.orderUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({
                            branch_id: this.branchId,
                            customer_name: this.customerName,
                            customer_phone: this.customerPhone,
                            idempotency_key: this.key,
                            items: this.items,
                        }),
                    });
                    const payload = await response.json();
                    this.busy = false;
                    if (response.ok) {
                        this.message = '#' + payload.data.number;
                        this.items = [];
                        this.key = null;
                        this.total = '';
                    } else if (response.status === 404) {
                        this.message = '404';
                    } else {
                        this.message = payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Error';
                    }
                },
            }));
        });
    </script>
@endsection
