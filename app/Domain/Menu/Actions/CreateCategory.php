<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\MenuCategory;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateCategory
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, parent_id?: ?int, sort_order?: int, is_active?: bool}  $data
     */
    public function handle(array $data): MenuCategory
    {
        $this->features->assertWritable();
        $this->assertDigitalMenu();

        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = MenuCategory::query()->find($parentId);

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => __('app.category_unavailable'),
                ]);
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => __('app.category_one_level'),
                ]);
            }
        }

        $category = MenuCategory::query()->create([
            'parent_id' => $parentId,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        PublicMenuCache::forgetTenant();

        return $category;
    }

    private function assertDigitalMenu(): void
    {
        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }
    }
}
