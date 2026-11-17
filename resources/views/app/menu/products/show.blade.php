@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $product->name() }}</h1>
        <a href="{{ route('app.menu.products.index') }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.products') }}</a>
    </div>

    <section class="mt-8 rounded-lg border border-line bg-white px-4 py-5">
        <h2 class="text-lg font-semibold">{{ __('app.product_details') }}</h2>
        <form method="POST" action="{{ route('app.menu.products.update', $product->public_id) }}" class="mt-4 max-w-xl space-y-4">
            @csrf
            @method('PUT')
            @include('app.menu.products._form', ['product' => $product, 'categories' => $categories, 'taxRates' => $taxRates, 'prepStations' => $prepStations, 'field' => $field])
            <button type="submit" class="flex min-h-11 items-center justify-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>
    </section>

    <section class="mt-8 rounded-lg border border-line bg-white px-4 py-5">
        <h2 class="text-lg font-semibold">{{ __('app.branch_availability') }}</h2>
        <p class="mt-1 text-sm text-ink/70">{{ __('app.branch_availability_hint') }}</p>
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($branches as $branch)
                @php($setting = $product->branchSetting($branch))
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2">
                    <span>{{ $branch->name() }}</span>
                    <span class="text-ink/70">
                        {{ $product->isAvailableAt($branch) ? __('app.available') : __('app.unavailable') }}
                        · {{ $product->priceAt($branch) }}
                        @if ($setting === null)
                            ({{ __('app.inherited') }})
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('app.menu.products.branches.store', $product->public_id) }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @csrf
            <select name="branch_id" required class="{{ $field }}">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name() }}</option>
                @endforeach
            </select>
            <input name="price_override" type="number" step="0.01" min="0" placeholder="{{ __('app.price_override') }}" class="{{ $field }}">
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_available" value="1" checked>
                {{ __('app.available') }}
            </label>
            <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>
    </section>

    <section class="mt-8 rounded-lg border border-line bg-white px-4 py-5">
        <h2 class="text-lg font-semibold">{{ __('app.product_images') }}</h2>
        <ul class="mt-4 grid gap-3 sm:grid-cols-3">
            @forelse ($product->images as $image)
                <li class="rounded-lg border border-line px-3 py-3 text-sm">
                    <p class="font-medium">{{ $image->public_id }}</p>
                    <p class="mt-1 text-ink/70">{{ $image->is_primary ? __('app.primary_image') : __('app.extra_image') }}</p>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_images') }}</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('app.menu.products.images.store', $product->public_id) }}" enctype="multipart/form-data" class="mt-4 space-y-3 max-w-xl">
            @csrf
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="{{ $field }}">
            @error('image')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_primary" value="1">
                {{ __('app.primary_image') }}
            </label>
            <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.upload_image') }}</button>
        </form>
    </section>

    @if (! empty($inventoryEnabled) && auth()->user()->hasPermission('inventory.items.manage'))
        <section class="mt-8 rounded-lg border border-line bg-white px-4 py-5">
            <h2 class="text-lg font-semibold">{{ __('app.recipe') }}</h2>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.recipe_hint') }}</p>
            @if ($product->recipe)
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($product->recipe->lines as $line)
                        <li class="border-b border-line py-2">
                            {{ $line->stockItem?->name() ?? $line->stock_item_id }}
                            · {{ $line->quantity }}
                            {{ $line->unit?->abbreviation }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm text-ink/70">{{ __('app.yield_qty') }}: {{ $product->recipe->yield_qty }}</p>
            @endif
            <form method="POST" action="{{ route('app.menu.products.recipe.store', $product->public_id) }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <input name="yield_qty" type="number" step="0.001" min="0.001" value="{{ old('yield_qty', $product->recipe?->yield_qty ?? '1') }}" placeholder="{{ __('app.yield_qty') }}" class="{{ $field }}">
                    <input name="notes" maxlength="500" value="{{ old('notes', $product->recipe?->notes) }}" placeholder="{{ __('app.notes') }}" class="{{ $field }}">
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <select name="lines[0][stock_item_id]" required class="{{ $field }}">
                        <option value="">{{ __('app.stock_item') }}</option>
                        @foreach ($stockItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name() }} ({{ $item->unit?->abbreviation }})</option>
                        @endforeach
                    </select>
                    <input name="lines[0][quantity]" type="number" step="0.001" min="0.001" required placeholder="{{ __('app.quantity') }}" class="{{ $field }}">
                    <select name="lines[0][unit_id]" required class="{{ $field }}">
                        <option value="">{{ __('app.unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name() }} ({{ $unit->abbreviation }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.save_recipe') }}</button>
            </form>
        </section>
    @endif

    <section class="mt-8 rounded-lg border border-line bg-white px-4 py-5">
        <h2 class="text-lg font-semibold">{{ __('app.option_groups') }}</h2>
        <div class="mt-4 space-y-6">
            @foreach ($product->optionGroups as $group)
                <div class="rounded-lg border border-line px-4 py-4">
                    <p class="font-semibold">{{ $group->name() }} · {{ $group->kind }} · {{ $group->selection }}</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($group->options as $option)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span>{{ $option->name() }} (+{{ $option->price_delta }}) @if($option->is_default) · {{ __('app.default_option') }} @endif</span>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('app.menu.options.store', $group->public_id) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                        @csrf
                        <input name="name_ar" placeholder="{{ __('app.name_ar') }}" required class="{{ $field }}">
                        <input name="name_en" placeholder="{{ __('app.name_en') }}" required class="{{ $field }}">
                        <input name="price_delta" type="number" step="0.01" min="0" value="0" class="{{ $field }}">
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" name="is_default" value="1">
                            {{ __('app.default_option') }}
                        </label>
                        <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink md:col-span-2">{{ __('app.add_option') }}</button>
                    </form>
                    @error('is_default')
                        <p class="mt-2 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('app.menu.products.option-groups.store', $product->public_id) }}" class="mt-6 grid gap-3 md:grid-cols-2 max-w-3xl">
            @csrf
            <input name="name_ar" placeholder="{{ __('app.name_ar') }}" required class="{{ $field }}">
            <input name="name_en" placeholder="{{ __('app.name_en') }}" required class="{{ $field }}">
            <select name="kind" required class="{{ $field }}">
                @foreach (['size', 'variant', 'modifier', 'addon'] as $kind)
                    <option value="{{ $kind }}">{{ __('app.option_kind_'.$kind) }}</option>
                @endforeach
            </select>
            <select name="selection" required class="{{ $field }}">
                <option value="single">{{ __('app.selection_single') }}</option>
                <option value="multiple">{{ __('app.selection_multiple') }}</option>
            </select>
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_required" value="1">
                {{ __('app.is_required') }}
            </label>
            <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.add_option_group') }}</button>
        </form>
    </section>
@endsection
