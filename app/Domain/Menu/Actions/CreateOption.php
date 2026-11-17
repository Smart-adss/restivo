<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\ConflictingDefaultOption;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;

class CreateOption
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *     name_ar: string,
     *     name_en: string,
     *     price_delta?: numeric-string|float|int,
     *     is_default?: bool,
     *     is_active?: bool,
     *     sort_order?: int
     * }  $data
     */
    public function handle(ProductOptionGroup $group, array $data): ProductOption
    {
        return DB::transaction(function () use ($group, $data): ProductOption {
            $this->features->assertWritable();

            if (! $this->features->allows('module.digital_menu')) {
                abort(404);
            }

            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault && $group->isSingle()) {
                $existingDefault = ProductOption::query()
                    ->where('group_id', $group->id)
                    ->where('is_default', true)
                    ->lockForUpdate()
                    ->exists();

                if ($existingDefault) {
                    throw new ConflictingDefaultOption;
                }
            }

            return ProductOption::query()->create([
                'group_id' => $group->id,
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'price_delta' => $data['price_delta'] ?? 0,
                'is_default' => $isDefault,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);
        });
    }
}
