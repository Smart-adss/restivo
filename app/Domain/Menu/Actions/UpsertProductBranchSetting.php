<?php

namespace App\Domain\Menu\Actions;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertProductBranchSetting
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{is_available: bool, price_override?: ?numeric-string|float|int|null}  $data
     */
    public function handle(Product $product, Branch $branch, array $data): ProductBranchSetting
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        if ((int) $branch->tenant_id !== (int) $product->tenant_id) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $payload = [
            'tenant_id' => app(TenantContext::class)->id(),
            'is_available' => $data['is_available'] ? 1 : 0,
            'price_override' => array_key_exists('price_override', $data) ? $data['price_override'] : null,
            'updated_at' => now(),
        ];

        $updated = DB::table('product_branch_settings')
            ->where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->update($payload);

        if ($updated === 0) {
            DB::table('product_branch_settings')->insert($payload + [
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'created_at' => now(),
            ]);
        }

        return ProductBranchSetting::query()
            ->where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();
    }
}
