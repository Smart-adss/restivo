<?php

namespace App\Domain\Menu\Actions;

use App\Models\Branch;
use App\Models\ProductOption;
use App\Models\ProductOptionBranchSetting;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertOptionBranchSetting
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{is_available: bool, price_delta_override?: ?numeric-string|float|int|null}  $data
     */
    public function handle(ProductOption $option, Branch $branch, array $data): ProductOptionBranchSetting
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        if ((int) $branch->tenant_id !== (int) $option->tenant_id) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $payload = [
            'tenant_id' => app(TenantContext::class)->id(),
            'is_available' => $data['is_available'] ? 1 : 0,
            'price_delta_override' => array_key_exists('price_delta_override', $data) ? $data['price_delta_override'] : null,
            'updated_at' => now(),
        ];

        $updated = DB::table('product_option_branch_settings')
            ->where('option_id', $option->id)
            ->where('branch_id', $branch->id)
            ->update($payload);

        if ($updated === 0) {
            DB::table('product_option_branch_settings')->insert($payload + [
                'option_id' => $option->id,
                'branch_id' => $branch->id,
                'created_at' => now(),
            ]);
        }

        return ProductOptionBranchSetting::query()
            ->where('option_id', $option->id)
            ->where('branch_id', $branch->id)
            ->firstOrFail();
    }
}
