<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\MenuCategory;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class UpdateCategory
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, parent_id?: ?int, sort_order?: int, is_active?: bool}  $data
     */
    public function handle(MenuCategory $category, array $data): MenuCategory
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id;

        if ($parentId !== null) {
            if ((int) $parentId === (int) $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('app.category_one_level'),
                ]);
            }

            $parent = MenuCategory::query()->find($parentId);

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => __('app.category_unavailable'),
                ]);
            }

            if ($parent->parent_id !== null || $category->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('app.category_one_level'),
                ]);
            }
        }

        $category->fill([
            'parent_id' => $parentId,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ])->save();

        PublicMenuCache::forgetTenant();

        return $category->refresh();
    }
}
