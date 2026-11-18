@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.inventory')" :description="__('app.inventory_page_hint')">
        <x-slot:actions>
            <form method="GET" class="flex items-end gap-2">
                <div>
                    <x-ui.label for="inventory-branch">{{ __('app.branch') }}</x-ui.label>
                    <x-ui.select id="inventory-branch" name="branch_id" onchange="this.form.submit()">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @if (auth()->user()->hasPermission('inventory.items.manage'))
        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <form method="POST" action="{{ route('app.inventory.units.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.add_unit')" />
                <x-ui.field name="name_ar" required maxlength="80" placeholder="{{ __('app.name_ar') }}" />
                <x-ui.field name="name_en" required maxlength="80" placeholder="{{ __('app.name_en') }}" />
                <x-ui.field name="abbreviation" required maxlength="20" placeholder="{{ __('app.abbreviation') }}" />
                <x-ui.select name="kind">
                    <option value="count">{{ __('app.unit_kind_count') }}</option>
                    <option value="weight">{{ __('app.unit_kind_weight') }}</option>
                    <option value="volume">{{ __('app.unit_kind_volume') }}</option>
                </x-ui.select>
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>

            <form method="POST" action="{{ route('app.inventory.conversions.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.add_unit_conversion')" />
                <x-ui.select name="from_unit_id" required>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="to_unit_id" required>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field name="factor" type="number" step="0.000001" min="0.000001" required placeholder="{{ __('app.factor') }}" class="tabular-nums" />
                @error('to_unit_id')
                    <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>

            <form method="POST" action="{{ route('app.inventory.items.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.add_stock_item')" />
                <x-ui.field name="name_ar" required maxlength="190" placeholder="{{ __('app.name_ar') }}" />
                <x-ui.field name="name_en" required maxlength="190" placeholder="{{ __('app.name_en') }}" />
                <x-ui.select name="unit_id" required>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="kind">
                    <option value="ingredient">{{ __('app.stock_kind_ingredient') }}</option>
                    <option value="packaging">{{ __('app.stock_kind_packaging') }}</option>
                    <option value="resale">{{ __('app.stock_kind_resale') }}</option>
                </x-ui.select>
                <x-ui.field name="sku" maxlength="64" placeholder="SKU" />
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>

            <form method="POST" action="{{ route('app.inventory.stockout-setting') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.block_on_stockout')" :description="__('app.block_on_stockout_hint')" />
                <label class="flex min-h-11 items-center gap-2 text-sm">
                    <input type="checkbox" name="block_on_stockout" value="1" @checked($block_on_stockout)>
                    {{ __('app.block_on_stockout_enable') }}
                </label>
                <x-ui.button type="submit" variant="secondary">{{ __('app.save') }}</x-ui.button>
            </form>
        </div>
    @endif

    @if (auth()->user()->hasPermission('inventory.movements.adjust'))
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('app.inventory.movements.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.record_stock_movement')" />
                <x-ui.select name="stock_item_id" required>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="kind">
                    <option value="adjustment">{{ __('app.stock_kind_adjustment') }}</option>
                    <option value="waste">{{ __('app.stock_kind_waste') }}</option>
                </x-ui.select>
                <x-ui.field name="quantity" type="number" step="0.001" required placeholder="{{ __('app.quantity') }}" class="tabular-nums" />
                <x-ui.field name="note" required maxlength="255" placeholder="{{ __('app.notes') }}" />
                <x-ui.button type="submit" variant="primary">{{ __('app.record') }}</x-ui.button>
            </form>

            <form method="POST" action="{{ route('app.inventory.recalculate') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <x-ui.section-title :title="__('app.recalculate_stock')" :description="__('app.recalculate_stock_hint')" />
                <x-ui.button type="submit" variant="secondary">{{ __('app.recalculate_stock') }}</x-ui.button>
            </form>
        </div>
    @endif

    <section class="mt-8">
        <x-ui.section-title :title="__('app.branch_stocks')" />
        <div class="ui-panel overflow-hidden">
            <ul>
                @forelse ($stocks as $stock)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 text-sm last:border-b-0">
                        <span>{{ $stock->stockItem?->name() }} · {{ $stock->stockItem?->unit?->abbreviation }}</span>
                        <span class="font-semibold tabular-nums">{{ $stock->quantity }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_branch_stocks') }}</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="mt-8">
        <x-ui.section-title :title="__('app.stock_ledger')" :description="__('app.stock_ledger_append_only')" />
        <div class="ui-panel overflow-hidden">
            <ul>
                @forelse ($movements as $movement)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 text-sm last:border-b-0">
                        <span>{{ $movement->stockItem?->name() }} · {{ __('app.stock_movement_'.$movement->kind) }}@if ($movement->note) — {{ $movement->note }}@endif</span>
                        <span class="font-semibold tabular-nums">{{ $movement->quantity }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_stock_movements') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
