<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\InvalidProductImage;
use App\Domain\Menu\Support\ProductImageProcessor;
use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Features\FeatureGate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreProductImage
{
    public function __construct(
        private FeatureGate $features,
        private ProductImageProcessor $processor,
    ) {}

    public function handle(Product $product, UploadedFile $file, bool $primary = false): ProductImage
    {
        return DB::transaction(function () use ($product, $file, $primary): ProductImage {
            $this->features->assertWritable();

            if (! $this->features->allows('module.digital_menu')) {
                abort(404);
            }

            $count = ProductImage::query()->where('product_id', $product->id)->count();
            $this->features->assertUnderCeiling('images.per_product', $count);

            $path = $this->processor->store($file);

            if ($primary) {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->update(['is_primary' => false]);
            }

            try {
                $image = ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'sort_order' => $count,
                    'is_primary' => $primary || $count === 0,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e instanceof InvalidProductImage ? $e : new InvalidProductImage($e->getMessage());
            }

            PublicMenuCache::forgetTenant();

            return $image;
        });
    }
}
