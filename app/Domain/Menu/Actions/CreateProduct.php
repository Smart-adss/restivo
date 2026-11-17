<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *     category_id: int,
     *     name_ar: string,
     *     name_en: string,
     *     base_price: numeric-string|float|int,
     *     description_ar?: ?string,
     *     description_en?: ?string,
     *     sku?: ?string,
     *     tax_rate_id?: ?int,
     *     prep_station_id?: ?int,
     *     is_active?: bool
     * }  $data
     */
    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $this->features->assertWritable();

            if (! $this->features->allows('module.digital_menu')) {
                abort(404);
            }

            $count = Product::query()->count();
            $this->features->assertUnderCeiling('products.max', $count);

            $category = MenuCategory::query()->find($data['category_id']);

            if ($category === null) {
                throw ValidationException::withMessages([
                    'category_id' => __('app.category_unavailable'),
                ]);
            }

            $taxRateId = $data['tax_rate_id'] ?? null;

            if ($taxRateId !== null && ! TaxRate::query()->whereKey($taxRateId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'tax_rate_id' => __('app.tax_rate_unavailable'),
                ]);
            }

            $prepStationId = $data['prep_station_id'] ?? null;

            if ($prepStationId !== null && ! \App\Models\PrepStation::query()->whereKey($prepStationId)->exists()) {
                throw ValidationException::withMessages([
                    'prep_station_id' => __('app.prep_station_unavailable'),
                ]);
            }

            $product = Product::query()->create([
                'category_id' => $category->id,
                'tax_rate_id' => $taxRateId,
                'prep_station_id' => $prepStationId,
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'description_ar' => $data['description_ar'] ?? null,
                'description_en' => $data['description_en'] ?? null,
                'base_price' => $data['base_price'],
                'sku' => $data['sku'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            PublicMenuCache::forgetTenant();

            return $product;
        });
    }
}
