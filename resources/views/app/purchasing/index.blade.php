@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.purchasing') }}</h1>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.purchasing.suppliers.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_supplier') }}</h2>
            <input name="name" required maxlength="190" placeholder="{{ __('app.name') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="phone" required maxlength="40" placeholder="{{ __('app.phone') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="email" type="email" maxlength="190" placeholder="{{ __('app.email') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>

        <form method="POST" action="{{ route('app.purchasing.orders.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_purchase_order') }}</h2>
            <select name="branch_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name() }}</option>
                @endforeach
            </select>
            <select name="supplier_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            <select name="lines[0][stock_item_id]" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name() }}</option>
                @endforeach
            </select>
            <input name="lines[0][quantity]" type="number" step="0.001" min="0.001" required placeholder="{{ __('app.quantity') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="lines[0][unit_cost]" type="number" step="0.0001" min="0" required placeholder="{{ __('app.unit_cost') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold">{{ __('app.purchase_orders') }}</h2>
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($orders as $order)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <a href="{{ route('app.purchasing.show', $order->public_id) }}" class="font-medium text-brand hover:underline">
                        #{{ $order->public_id }} · {{ $order->supplier?->name }}
                    </a>
                    <span>{{ __('app.purchase_status_'.$order->status) }} · {{ $order->total }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_purchase_orders') }}</li>
            @endforelse
        </ul>
    </section>
@endsection
