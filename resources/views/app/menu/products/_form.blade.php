<div>
    <x-ui.label for="category_id">{{ __('app.category') }}</x-ui.label>
    <x-ui.select id="category_id" name="category_id" required>
        <option value="">{{ __('app.choose') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name() }}</option>
        @endforeach
    </x-ui.select>
    @error('category_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="name_ar">{{ __('app.name_ar') }}</x-ui.label>
    <x-ui.field id="name_ar" name="name_ar" value="{{ old('name_ar', $product?->name_ar) }}" required />
    @error('name_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="name_en">{{ __('app.name_en') }}</x-ui.label>
    <x-ui.field id="name_en" name="name_en" value="{{ old('name_en', $product?->name_en) }}" required />
    @error('name_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="description_ar">{{ __('app.description_ar') }}</x-ui.label>
    <x-ui.textarea id="description_ar" name="description_ar" rows="3">{{ old('description_ar', $product?->description_ar) }}</x-ui.textarea>
    @error('description_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="description_en">{{ __('app.description_en') }}</x-ui.label>
    <x-ui.textarea id="description_en" name="description_en" rows="3">{{ old('description_en', $product?->description_en) }}</x-ui.textarea>
    @error('description_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="base_price">{{ __('app.base_price') }}</x-ui.label>
    <x-ui.field id="base_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price', $product?->base_price) }}" required class="tabular-nums" />
    @error('base_price')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="sku">{{ __('app.sku') }}</x-ui.label>
    <x-ui.field id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" />
    @error('sku')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="tax_rate_id">{{ __('app.tax_rate') }}</x-ui.label>
    <x-ui.select id="tax_rate_id" name="tax_rate_id">
        <option value="">{{ __('app.no_tax') }}</option>
        @foreach ($taxRates as $taxRate)
            <option value="{{ $taxRate->id }}" @selected((string) old('tax_rate_id', $product?->tax_rate_id) === (string) $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->rate_percent }}%)</option>
        @endforeach
    </x-ui.select>
    @error('tax_rate_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="prep_station_id">{{ __('app.prep_station') }}</x-ui.label>
    <x-ui.select id="prep_station_id" name="prep_station_id">
        <option value="">{{ __('app.no_prep_station') }}</option>
        @foreach (($prepStations ?? []) as $station)
            <option value="{{ $station->id }}" @selected((string) old('prep_station_id', $product?->prep_station_id) === (string) $station->id)>{{ $station->name() }}</option>
        @endforeach
    </x-ui.select>
    @error('prep_station_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<label class="flex min-h-11 items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
    {{ __('app.is_active') }}
</label>
