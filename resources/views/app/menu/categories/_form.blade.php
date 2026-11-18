<div>
    <x-ui.label for="name_ar">{{ __('app.name_ar') }}</x-ui.label>
    <x-ui.field id="name_ar" name="name_ar" value="{{ old('name_ar', $category?->name_ar) }}" required />
    @error('name_ar')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="name_en">{{ __('app.name_en') }}</x-ui.label>
    <x-ui.field id="name_en" name="name_en" value="{{ old('name_en', $category?->name_en) }}" required />
    @error('name_en')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="parent_id">{{ __('app.parent_category') }}</x-ui.label>
    <x-ui.select id="parent_id" name="parent_id">
        <option value="">{{ __('app.no_parent') }}</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parent->id)>{{ $parent->name() }}</option>
        @endforeach
    </x-ui.select>
    @error('parent_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <x-ui.label for="sort_order">{{ __('app.sort_order') }}</x-ui.label>
    <x-ui.field id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" class="tabular-nums" />
    @error('sort_order')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<label class="flex min-h-11 items-center gap-2 text-sm">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))>
    {{ __('app.is_active') }}
</label>
