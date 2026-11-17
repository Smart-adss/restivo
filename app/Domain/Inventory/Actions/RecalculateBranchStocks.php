<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;

class RecalculateBranchStocks
{
    public function __construct(
        private FeatureGate $features,
        private EnsureBranchStock $ensureBranchStock,
    ) {}

    /**
     * @return array{updated: int}
     */
    public function handle(?int $branchId = null): array
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.movements.adjust'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        $branches = Branch::query()
            ->when($branchId !== null, fn ($q) => $q->whereKey($branchId))
            ->get()
            ->filter(fn (Branch $branch) => auth()->user()->canAccessBranch($branch));

        $updated = 0;

        DB::transaction(function () use ($branches, &$updated): void {
            $items = StockItem::query()->pluck('id');

            foreach ($branches as $branch) {
                foreach ($items as $itemId) {
                    $this->ensureBranchStock->handle($branch->id, (int) $itemId);

                    $sum = StockMovement::query()
                        ->where('branch_id', $branch->id)
                        ->where('stock_item_id', $itemId)
                        ->sum('quantity');

                    $quantity = number_format((float) $sum, 3, '.', '');

                    BranchStock::query()
                        ->where('branch_id', $branch->id)
                        ->where('stock_item_id', $itemId)
                        ->update([
                            'quantity' => $quantity,
                            'updated_at' => now(),
                        ]);

                    $updated++;
                }
            }
        });

        return ['updated' => $updated];
    }
}
