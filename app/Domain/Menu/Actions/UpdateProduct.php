<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class UpdateProduct
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
     *     is_active?: bool
     * }  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        $category = MenuCategory::query()->find($data['category_id']);

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => __('app.category_unavailable'),
            ]);
        }

        $taxRateId = array_key_exists('tax_rate_id', $data) ? $data['tax_rate_id'] : $product->tax_rate_id;

        if ($taxRateId !== null && ! TaxRate::query()->whereKey($taxRateId)->exists()) {
            throw ValidationException::withMessages([
                'tax_rate_id' => __('app.tax_rate_unavailable'),
            ]);
        }

        $product->fill([
            'category_id' => $category->id,
            'tax_rate_id' => $taxRateId,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'base_price' => $data['base_price'],
            'sku' => $data['sku'] ?? null,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ])->save();

        PublicMenuCache::forgetTenant();

        return $product->refresh();
    }
}
