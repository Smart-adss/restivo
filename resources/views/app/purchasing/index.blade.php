@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.purchasing')" :description="__('app.purchasing_page_hint')" />

    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.purchasing.suppliers.store') }}" class="ui-panel space-y-3 p-4">
            @csrf
            <x-ui.section-title :title="__('app.add_supplier')" />
            <x-ui.field name="name" required maxlength="190" placeholder="{{ __('app.name') }}" />
            <x-ui.field name="phone" required maxlength="40" placeholder="{{ __('app.phone') }}" />
            <x-ui.field name="email" type="email" maxlength="190" placeholder="{{ __('app.email') }}" />
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('app.purchasing.orders.store') }}" class="ui-panel space-y-3 p-4">
            @csrf
            <x-ui.section-title :title="__('app.add_purchase_order')" />
            <x-ui.select name="branch_id" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="supplier_id" required>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="lines[0][stock_item_id]" required>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.field name="lines[0][quantity]" type="number" step="0.001" min="0.001" required placeholder="{{ __('app.quantity') }}" class="tabular-nums" />
            <x-ui.field name="lines[0][unit_cost]" type="number" step="0.0001" min="0" required placeholder="{{ __('app.unit_cost') }}" class="tabular-nums" />
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </div>

    <section class="mt-8">
        <x-ui.section-title :title="__('app.purchase_orders')" />
        <div class="ui-panel overflow-hidden">
            <ul>
                @forelse ($orders as $order)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 text-sm last:border-b-0">
                        <a href="{{ route('app.purchasing.show', $order->public_id) }}" class="font-semibold text-brand hover:underline">
                            #{{ $order->public_id }} · {{ $order->supplier?->name }}
                        </a>
                        <span>{{ __('app.purchase_status_'.$order->status) }} · <span class="tabular-nums">{{ $order->total }}</span></span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_purchase_orders') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
