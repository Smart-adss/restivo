@extends('layouts.app')

@section('content')
    @php($field = 'ui-field')
    <x-ui.page-header :title="$product->name()" :description="$product->category?->name()">
        <x-slot:actions>
            <x-ui.button :href="route('app.menu.products.index')" variant="ghost">{{ __('app.products') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="ui-panel px-4 py-5">
        <x-ui.section-title :title="__('app.product_details')" />
        <form method="POST" action="{{ route('app.menu.products.update', $product->public_id) }}" class="mt-4 max-w-xl space-y-4">
            @csrf
            @method('PUT')
            @include('app.menu.products._form', ['product' => $product, 'categories' => $categories, 'taxRates' => $taxRates, 'prepStations' => $prepStations, 'field' => $field])
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </section>

    <section class="ui-panel mt-6 px-4 py-5">
        <x-ui.section-title :title="__('app.branch_availability')" :description="__('app.branch_availability_hint')" />
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($branches as $branch)
                @php($setting = $product->branchSetting($branch))
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2">
                    <span>{{ $branch->name() }}</span>
                    <span class="text-ink/70">
                        {{ $product->isAvailableAt($branch) ? __('app.available') : __('app.unavailable') }}
                        · <span class="tabular-nums">{{ $product->priceAt($branch) }}</span>
                        @if ($setting === null)
                            ({{ __('app.inherited') }})
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('app.menu.products.branches.store', $product->public_id) }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @csrf
            <x-ui.select name="branch_id" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.field name="price_override" type="number" step="0.01" min="0" placeholder="{{ __('app.price_override') }}" class="tabular-nums" />
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_available" value="1" checked>
                {{ __('app.available') }}
            </label>
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </section>

    <section class="ui-panel mt-6 px-4 py-5">
        <x-ui.section-title :title="__('app.product_images')" />
        <ul class="mt-4 grid gap-3 sm:grid-cols-3">
            @forelse ($product->images as $image)
                <li class="rounded-md border border-line px-3 py-3 text-sm">
                    <p class="font-medium tabular-nums">{{ $image->public_id }}</p>
                    <p class="mt-1 text-ink/70">{{ $image->is_primary ? __('app.primary_image') : __('app.extra_image') }}</p>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_images') }}</li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('app.menu.products.images.store', $product->public_id) }}" enctype="multipart/form-data" class="mt-4 max-w-xl space-y-3">
            @csrf
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="ui-field">
            @error('image')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_primary" value="1">
                {{ __('app.primary_image') }}
            </label>
            <x-ui.button type="submit" variant="primary">{{ __('app.upload_image') }}</x-ui.button>
        </form>
    </section>

    @if (! empty($inventoryEnabled) && auth()->user()->hasPermission('inventory.items.manage'))
        <section class="ui-panel mt-6 px-4 py-5">
            <x-ui.section-title :title="__('app.recipe')" :description="__('app.recipe_hint')" />
            @if ($product->recipe)
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($product->recipe->lines as $line)
                        <li class="border-b border-line py-2">
                            {{ $line->stockItem?->name() ?? $line->stock_item_id }}
                            · <span class="tabular-nums">{{ $line->quantity }}</span>
                            {{ $line->unit?->abbreviation }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm text-ink/70">{{ __('app.yield_qty') }}: <span class="tabular-nums">{{ $product->recipe->yield_qty }}</span></p>
            @endif
            <form method="POST" action="{{ route('app.menu.products.recipe.store', $product->public_id) }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <x-ui.field name="yield_qty" type="number" step="0.001" min="0.001" value="{{ old('yield_qty', $product->recipe?->yield_qty ?? '1') }}" placeholder="{{ __('app.yield_qty') }}" class="tabular-nums" />
                    <x-ui.field name="notes" maxlength="500" value="{{ old('notes', $product->recipe?->notes) }}" placeholder="{{ __('app.notes') }}" />
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <x-ui.select name="lines[0][stock_item_id]" required>
                        <option value="">{{ __('app.stock_item') }}</option>
                        @foreach ($stockItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name() }} ({{ $item->unit?->abbreviation }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field name="lines[0][quantity]" type="number" step="0.001" min="0.001" required placeholder="{{ __('app.quantity') }}" class="tabular-nums" />
                    <x-ui.select name="lines[0][unit_id]" required>
                        <option value="">{{ __('app.unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name() }} ({{ $unit->abbreviation }})</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="primary">{{ __('app.save_recipe') }}</x-ui.button>
            </form>
        </section>
    @endif

    <section class="ui-panel mt-6 px-4 py-5">
        <x-ui.section-title :title="__('app.option_groups')" />
        <div class="mt-4 space-y-6">
            @foreach ($product->optionGroups as $group)
                <div class="rounded-md border border-line px-4 py-4">
                    <p class="font-semibold">{{ $group->name() }} · {{ $group->kind }} · {{ $group->selection }}</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($group->options as $option)
                            <li class="flex flex-wrap items-center justify-between gap-2">
                                <span>{{ $option->name() }} (+<span class="tabular-nums">{{ $option->price_delta }}</span>) @if($option->is_default) · {{ __('app.default_option') }} @endif</span>
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('app.menu.options.store', $group->public_id) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                        @csrf
                        <x-ui.field name="name_ar" placeholder="{{ __('app.name_ar') }}" required />
                        <x-ui.field name="name_en" placeholder="{{ __('app.name_en') }}" required />
                        <x-ui.field name="price_delta" type="number" step="0.01" min="0" value="0" class="tabular-nums" />
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" name="is_default" value="1">
                            {{ __('app.default_option') }}
                        </label>
                        <x-ui.button type="submit" variant="primary" class="md:col-span-2">{{ __('app.add_option') }}</x-ui.button>
                    </form>
                    @error('is_default')
                        <p class="mt-2 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('app.menu.products.option-groups.store', $product->public_id) }}" class="mt-6 grid max-w-3xl gap-3 md:grid-cols-2">
            @csrf
            <x-ui.field name="name_ar" placeholder="{{ __('app.name_ar') }}" required />
            <x-ui.field name="name_en" placeholder="{{ __('app.name_en') }}" required />
            <x-ui.select name="kind" required>
                @foreach (['size', 'variant', 'modifier', 'addon'] as $kind)
                    <option value="{{ $kind }}">{{ __('app.option_kind_'.$kind) }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="selection" required>
                <option value="single">{{ __('app.selection_single') }}</option>
                <option value="multiple">{{ __('app.selection_multiple') }}</option>
            </x-ui.select>
            <label class="flex min-h-11 items-center gap-2 text-sm">
                <input type="checkbox" name="is_required" value="1">
                {{ __('app.is_required') }}
            </label>
            <x-ui.button type="submit" variant="primary">{{ __('app.add_option_group') }}</x-ui.button>
        </form>
    </section>
@endsection
