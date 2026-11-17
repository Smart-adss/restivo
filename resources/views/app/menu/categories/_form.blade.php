<div>
    <label for="name_ar" class="mb-1 block text-sm">{{ __('app.name_ar') }}</label>
    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $category?->name_ar) }}" required class="{{ $field }}">
    @error('name_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="name_en" class="mb-1 block text-sm">{{ __('app.name_en') }}</label>
    <input id="name_en" name="name_en" value="{{ old('name_en', $category?->name_en) }}" required class="{{ $field }}">
    @error('name_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="parent_id" class="mb-1 block text-sm">{{ __('app.parent_category') }}</label>
    <select id="parent_id" name="parent_id" class="{{ $field }}">
        <option value="">{{ __('app.no_parent') }}</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parent->id)>{{ $parent->name() }}</option>
        @endforeach
    </select>
    @error('parent_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <label for="sort_order" class="mb-1 block text-sm">{{ __('app.sort_order') }}</label>
    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" class="{{ $field }}">
    @error('sort_order')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<label class="flex min-h-11 items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))>
    {{ __('app.is_active') }}
</label>
