@extends('layouts.ops')

@section('ops-title', __('app.pos'))
@section('ops-context', $branch->name())

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('app.pos') }}" class="flex items-end gap-2">
            <div>
                <x-ui.label for="pos-branch">{{ __('app.branch') }}</x-ui.label>
                <x-ui.select id="pos-branch" name="branch_id" class="min-w-[12rem]" onchange="this.form.submit()">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </form>
    </div>

    @error('shift')
        <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    @unless ($openShift)
        <x-ui.panel class="max-w-3xl">
            <h2 class="text-lg font-semibold tracking-tight">{{ __('app.open_shift') }}</h2>
            <p class="mt-1 text-sm text-ink/65">{{ __('app.pos_open_shift_hint') }}</p>
            <form method="POST" action="{{ route('app.pos.shifts.open') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div>
                    <x-ui.label for="register_id">{{ __('app.register') }}</x-ui.label>
                    <x-ui.select id="register_id" name="register_id" required>
                        @foreach ($registers as $register)
                            <option value="{{ $register->id }}">{{ $register->name }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="opening_float">{{ __('app.opening_float') }}</x-ui.label>
                    <x-ui.field id="opening_float" type="number" step="0.01" min="0" name="opening_float" value="0" required class="tabular-nums" />
                </div>
                <x-ui.button type="submit" variant="primary" class="self-end">{{ __('app.open_shift') }}</x-ui.button>
            </form>
        </x-ui.panel>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 ui-panel px-4 py-3 text-sm">
            <p>
                <span class="font-semibold">{{ $openShift->register->name }}</span>
                <span class="text-ink/55"> · {{ __('app.opening_float') }} </span>
                <span class="tabular-nums">{{ $openShift->opening_float }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('app.pos.movements.store', $openShift->public_id) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <x-ui.select name="kind" class="!min-h-11 w-auto">
                        <option value="pay_in">{{ __('app.pay_in') }}</option>
                        <option value="pay_out">{{ __('app.pay_out') }}</option>
                    </x-ui.select>
                    <x-ui.field type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="w-28 tabular-nums" />
                    <x-ui.button type="submit" variant="secondary">{{ __('app.record') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('app.pos.shifts.close', $openShift->public_id) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <x-ui.field type="number" step="0.01" min="0" name="counted_cash" required placeholder="{{ __('app.counted_cash') }}" class="w-36 tabular-nums" />
                    <x-ui.button type="submit" variant="danger">{{ __('app.close_shift') }}</x-ui.button>
                </form>
            </div>
        </div>

        <div
            x-data="posSale({
                products: {{ Js::from($products) }},
                shiftId: {{ $openShift->id }},
                saleUrl: {{ Js::from(route('app.pos.sales.store')) }},
                locale: {{ Js::from($locale) }},
                csrf: {{ Js::from(csrf_token()) }},
                labels: {
                    requiredOptions: {{ Js::from(__('app.pos_required_options')) }},
                    networkError: {{ Js::from(__('app.pos_network_error')) }},
                    saleOk: {{ Js::from(__('app.pos_sale_recorded')) }},
                    searchPlaceholder: {{ Js::from(__('app.pos_search_products')) }},
                }
            })"
            class="grid gap-4 xl:grid-cols-[1.55fr_22rem]"
        >
            <section class="ui-panel flex min-h-[28rem] flex-col px-4 py-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold tracking-tight">{{ __('app.products') }}</h2>
                    <x-ui.field
                        type="search"
                        x-model="query"
                        :placeholder="labels.searchPlaceholder"
                        class="max-w-xs"
                        autocomplete="off"
                    />
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    <template x-for="product in filteredProducts()" :key="product.id">
                        <button type="button" class="ops-product-tile" @click="addProduct(product)">
                            <span class="block text-sm font-semibold leading-snug" x-text="locale === 'ar' ? product.name_ar : product.name_en"></span>
                            <span class="mt-3 block text-sm font-semibold tabular-nums text-brand" x-text="product.price"></span>
                        </button>
                    </template>
                </div>
                <p class="mt-4 text-sm text-ink/55" x-show="filteredProducts().length === 0">{{ __('app.pos_no_products_match') }}</p>
            </section>

            <section class="ops-cart-shell px-4 py-4">
                <h2 class="font-semibold tracking-tight">{{ __('app.cart') }}</h2>
                <ul class="mt-3 max-h-[18rem] flex-1 space-y-2 overflow-y-auto text-sm">
                    <template x-for="(line, index) in cart" :key="index">
                        <li class="rounded-md border border-line px-3 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold" x-text="lineLabel(line)"></p>
                                <button type="button" class="text-danger" @click="removeLine(index)" aria-label="{{ __('app.remove') }}">×</button>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button" class="ops-qty-btn" @click="changeQty(index, -1)">−</button>
                                <span class="min-w-8 text-center tabular-nums font-semibold" x-text="line.quantity"></span>
                                <button type="button" class="ops-qty-btn" @click="changeQty(index, 1)">+</button>
                            </div>
                        </li>
                    </template>
                </ul>
                <p class="mt-2 text-sm text-ink/50" x-show="cart.length === 0">{{ __('app.pos_cart_empty') }}</p>

                <div class="mt-4 space-y-2 border-t border-line pt-4">
                    <div>
                        <x-ui.label>{{ __('app.cash') }}</x-ui.label>
                        <input type="number" step="0.01" min="0" x-model="cashAmount" class="ui-field tabular-nums">
                    </div>
                    <div>
                        <x-ui.label>{{ __('app.card') }}</x-ui.label>
                        <input type="number" step="0.01" min="0" x-model="cardAmount" class="ui-field tabular-nums">
                    </div>
                </div>

                <p class="mt-3 text-xs text-ink/60" x-show="pending">{{ __('app.pos_sending') }}</p>
                <p class="mt-2 text-sm text-danger" x-text="error" x-show="error"></p>
                <p class="mt-2 text-sm text-brand" x-show="success" x-text="success"></p>
                <a x-show="receiptUrl" :href="receiptUrl" target="_blank" class="mt-2 inline-block text-sm font-semibold text-brand underline">{{ __('app.view_receipt') }}</a>

                <button
                    type="button"
                    class="ui-btn ui-btn-primary mt-4 w-full min-h-12"
                    :disabled="pending || cart.length === 0"
                    @click="checkout()"
                >
                    {{ __('app.charge') }}
                </button>
            </section>
        </div>
    @endunless
@endsection
