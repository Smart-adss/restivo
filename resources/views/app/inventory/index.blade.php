@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.inventory') }}</h1>
        <form method="GET" class="flex items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3" onchange="this.form.submit()">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if (auth()->user()->hasPermission('inventory.items.manage'))
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <form method="POST" action="{{ route('app.inventory.units.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h2 class="font-semibold">{{ __('app.add_unit') }}</h2>
                <input name="name_ar" required maxlength="80" placeholder="{{ __('app.name_ar') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <input name="name_en" required maxlength="80" placeholder="{{ __('app.name_en') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <input name="abbreviation" required maxlength="20" placeholder="{{ __('app.abbreviation') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <select name="kind" class="min-h-11 w-full rounded-lg border border-line px-3">
                    <option value="count">{{ __('app.unit_kind_count') }}</option>
                    <option value="weight">{{ __('app.unit_kind_weight') }}</option>
                    <option value="volume">{{ __('app.unit_kind_volume') }}</option>
                </select>
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>

            <form method="POST" action="{{ route('app.inventory.conversions.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h2 class="font-semibold">{{ __('app.add_unit_conversion') }}</h2>
                <select name="from_unit_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </select>
                <select name="to_unit_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </select>
                <input name="factor" type="number" step="0.000001" min="0.000001" required placeholder="{{ __('app.factor') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                @error('to_unit_id')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>

            <form method="POST" action="{{ route('app.inventory.items.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h2 class="font-semibold">{{ __('app.add_stock_item') }}</h2>
                <input name="name_ar" required maxlength="190" placeholder="{{ __('app.name_ar') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <input name="name_en" required maxlength="190" placeholder="{{ __('app.name_en') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <select name="unit_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name() }}</option>
                    @endforeach
                </select>
                <select name="kind" class="min-h-11 w-full rounded-lg border border-line px-3">
                    <option value="ingredient">{{ __('app.stock_kind_ingredient') }}</option>
                    <option value="packaging">{{ __('app.stock_kind_packaging') }}</option>
                    <option value="resale">{{ __('app.stock_kind_resale') }}</option>
                </select>
                <input name="sku" maxlength="64" placeholder="SKU" class="min-h-11 w-full rounded-lg border border-line px-3">
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>
        </div>
    @endif

    @if (auth()->user()->hasPermission('inventory.movements.adjust'))
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('app.inventory.movements.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h2 class="font-semibold">{{ __('app.record_stock_movement') }}</h2>
                <select name="stock_item_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->name() }}</option>
                    @endforeach
                </select>
                <select name="kind" class="min-h-11 w-full rounded-lg border border-line px-3">
                    <option value="adjustment">{{ __('app.stock_kind_adjustment') }}</option>
                    <option value="waste">{{ __('app.stock_kind_waste') }}</option>
                </select>
                <input name="quantity" type="number" step="0.001" required class="min-h-11 w-full rounded-lg border border-line px-3" placeholder="{{ __('app.quantity') }}">
                <input name="note" required maxlength="255" class="min-h-11 w-full rounded-lg border border-line px-3" placeholder="{{ __('app.notes') }}">
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.record') }}</button>
            </form>

            <form method="POST" action="{{ route('app.inventory.recalculate') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <h2 class="font-semibold">{{ __('app.recalculate_stock') }}</h2>
                <p class="text-sm text-ink/70">{{ __('app.recalculate_stock_hint') }}</p>
                <button class="min-h-11 rounded-lg border border-line px-4 text-sm">{{ __('app.recalculate_stock') }}</button>
            </form>
        </div>
    @endif

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold">{{ __('app.branch_stocks') }}</h2>
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($stocks as $stock)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <span>{{ $stock->stockItem?->name() }} · {{ $stock->stockItem?->unit?->abbreviation }}</span>
                    <span class="font-semibold tabular-nums">{{ $stock->quantity }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_branch_stocks') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold">{{ __('app.stock_ledger') }}</h2>
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($movements as $movement)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <span>{{ $movement->stockItem?->name() }} · {{ __('app.stock_movement_'.$movement->kind) }}@if ($movement->note) — {{ $movement->note }}@endif</span>
                    <span class="font-semibold tabular-nums">{{ $movement->quantity }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_stock_movements') }}</li>
            @endforelse
        </ul>
        <p class="mt-2 text-xs text-ink/50">{{ __('app.stock_ledger_append_only') }}</p>
    </section>
@endsection
