<?php

namespace App\Domain\Inventory\Actions;

use App\Models\BranchStock;
use Illuminate\Support\Facades\DB;

class EnsureBranchStock
{
    public function handle(int $branchId, int $stockItemId): BranchStock
    {
        $existing = BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('stock_item_id', $stockItemId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        DB::table('branch_stocks')->insert([
            'tenant_id' => app(\App\Support\Tenancy\TenantContext::class)->id(),
            'branch_id' => $branchId,
            'stock_item_id' => $stockItemId,
            'quantity' => '0.000',
            'reorder_level' => null,
            'avg_cost' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return BranchStock::query()
            ->where('branch_id', $branchId)
            ->where('stock_item_id', $stockItemId)
            ->firstOrFail();
    }
}
