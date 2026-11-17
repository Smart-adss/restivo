<div>
    <label for="category_id" class="mb-1 block text-sm">{{ __('app.category') }}</label>
    <select id="category_id" name="category_id" required class="{{ $field }}">
        <option value="">{{ __('app.choose') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>{{ $category->name() }}</option>
        @endforeach
    </select>
    @error('category_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="name_ar" class="mb-1 block text-sm">{{ __('app.name_ar') }}</label>
    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $product?->name_ar) }}" required class="{{ $field }}">
    @error('name_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="name_en" class="mb-1 block text-sm">{{ __('app.name_en') }}</label>
    <input id="name_en" name="name_en" value="{{ old('name_en', $product?->name_en) }}" required class="{{ $field }}">
    @error('name_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="description_ar" class="mb-1 block text-sm">{{ __('app.description_ar') }}</label>
    <textarea id="description_ar" name="description_ar" rows="3" class="{{ $field }}">{{ old('description_ar', $product?->description_ar) }}</textarea>
    @error('description_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="description_en" class="mb-1 block text-sm">{{ __('app.description_en') }}</label>
    <textarea id="description_en" name="description_en" rows="3" class="{{ $field }}">{{ old('description_en', $product?->description_en) }}</textarea>
    @error('description_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="base_price" class="mb-1 block text-sm">{{ __('app.base_price') }}</label>
    <input id="base_price" name="base_price" type="number" step="0.01" min="0" value="{{ old('base_price', $product?->base_price) }}" required class="{{ $field }}">
    @error('base_price')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="sku" class="mb-1 block text-sm">{{ __('app.sku') }}</label>
    <input id="sku" name="sku" value="{{ old('sku', $product?->sku) }}" class="{{ $field }}">
    @error('sku')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="tax_rate_id" class="mb-1 block text-sm">{{ __('app.tax_rate') }}</label>
    <select id="tax_rate_id" name="tax_rate_id" class="{{ $field }}">
        <option value="">{{ __('app.no_tax') }}</option>
        @foreach ($taxRates as $taxRate)
            <option value="{{ $taxRate->id }}" @selected((string) old('tax_rate_id', $product?->tax_rate_id) === (string) $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->rate_percent }}%)</option>
        @endforeach
    </select>
    @error('tax_rate_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="prep_station_id" class="mb-1 block text-sm">{{ __('app.prep_station') }}</label>
    <select id="prep_station_id" name="prep_station_id" class="{{ $field }}">
        <option value="">{{ __('app.no_prep_station') }}</option>
        @foreach (($prepStations ?? []) as $station)
            <option value="{{ $station->id }}" @selected((string) old('prep_station_id', $product?->prep_station_id) === (string) $station->id)>{{ $station->name() }}</option>
        @endforeach
    </select>
    @error('prep_station_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<label class="flex min-h-11 items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product?->is_active ?? true))>
    {{ __('app.is_active') }}
</label>
