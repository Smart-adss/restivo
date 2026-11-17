@extends('layouts.app')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('app.pos') }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ $branch->name() }}</p>
        </div>
        <form method="GET" action="{{ route('app.pos') }}" class="flex items-end gap-2">
            <label class="text-sm">{{ __('app.branch') }}
                <select name="branch_id" class="mt-1 min-h-11 rounded-lg border border-line px-3" onchange="this.form.submit()">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    @error('shift')
        <p class="mb-4 rounded-lg border border-s-4 border-danger bg-white px-4 py-3 text-sm text-danger">{{ $message }}</p>
    @enderror

    @unless ($openShift)
        <section class="mb-6 rounded-lg border border-line bg-white px-4 py-4">
            <h2 class="font-semibold">{{ __('app.open_shift') }}</h2>
            <form method="POST" action="{{ route('app.pos.shifts.open') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <label class="text-sm">{{ __('app.register') }}
                    <select name="register_id" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                        @foreach ($registers as $register)
                            <option value="{{ $register->id }}">{{ $register->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">{{ __('app.opening_float') }}
                    <input type="number" step="0.01" min="0" name="opening_float" value="0" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                </label>
                <button type="submit" class="min-h-11 self-end rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.open_shift') }}</button>
            </form>
        </section>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-white px-4 py-3 text-sm">
            <p>{{ __('app.open_shift') }}: {{ $openShift->register->name }} · {{ __('app.opening_float') }} {{ $openShift->opening_float }}</p>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('app.pos.movements.store', $openShift->public_id) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <select name="kind" class="min-h-11 rounded-lg border border-line px-2">
                        <option value="pay_in">{{ __('app.pay_in') }}</option>
                        <option value="pay_out">{{ __('app.pay_out') }}</option>
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" class="min-h-11 w-28 rounded-lg border border-line px-2">
                    <button class="min-h-11 rounded-lg border border-line px-3">{{ __('app.record') }}</button>
                </form>
                <form method="POST" action="{{ route('app.pos.shifts.close', $openShift->public_id) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <input type="number" step="0.01" min="0" name="counted_cash" required placeholder="{{ __('app.counted_cash') }}" class="min-h-11 w-36 rounded-lg border border-line px-2">
                    <button class="min-h-11 rounded-lg border border-danger px-3 text-danger">{{ __('app.close_shift') }}</button>
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
                }
            })"
            class="grid gap-4 lg:grid-cols-[1.4fr_1fr]"
        >
            <section class="rounded-lg border border-line bg-white px-4 py-4">
                <h2 class="mb-3 font-semibold">{{ __('app.products') }}</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <template x-for="product in products" :key="product.id">
                        <button type="button" class="min-h-20 rounded-lg border border-line px-3 py-3 text-start hover:bg-canvas" @click="addProduct(product)">
                            <span class="block font-semibold" x-text="locale === 'ar' ? product.name_ar : product.name_en"></span>
                            <span class="mt-1 block text-sm tabular-nums" x-text="product.price"></span>
                        </button>
                    </template>
                </div>
            </section>

            <section class="rounded-lg border border-line bg-white px-4 py-4">
                <h2 class="mb-3 font-semibold">{{ __('app.cart') }}</h2>
                <ul class="mb-3 max-h-64 space-y-2 overflow-y-auto text-sm">
                    <template x-for="(line, index) in cart" :key="index">
                        <li class="flex items-start justify-between gap-2 border-b border-line pb-2">
                            <div>
                                <p class="font-semibold" x-text="lineLabel(line)"></p>
                                <p class="text-ink/60" x-text="'× ' + line.quantity"></p>
                            </div>
                            <button type="button" class="text-danger" @click="cart.splice(index, 1)">×</button>
                        </li>
                    </template>
                </ul>

                <div class="mb-3 space-y-2">
                    <label class="block text-sm">{{ __('app.cash') }}
                        <input type="number" step="0.01" min="0" x-model="cashAmount" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                    </label>
                    <label class="block text-sm">{{ __('app.card') }}
                        <input type="number" step="0.01" min="0" x-model="cardAmount" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
                    </label>
                </div>

                <p class="mb-2 text-xs text-ink/60" x-show="pending">{{ __('app.pos_sending') }}</p>
                <p class="mb-2 text-sm text-danger" x-text="error" x-show="error"></p>
                <p class="mb-2 text-sm text-brand" x-show="success" x-text="success"></p>
                <a x-show="receiptUrl" :href="receiptUrl" target="_blank" class="mb-3 inline-block text-sm underline">{{ __('app.view_receipt') }}</a>

                <button type="button" class="w-full min-h-11 rounded-lg bg-brand font-semibold text-brand-ink disabled:opacity-50" :disabled="pending || cart.length === 0" @click="checkout()">
                    {{ __('app.charge') }}
                </button>
            </section>
        </div>
    @endunless
@endsection
