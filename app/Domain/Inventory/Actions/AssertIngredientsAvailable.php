<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Support\UnitConverter;
use App\Models\BranchStock;
use App\Models\Recipe;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssertIngredientsAvailable
{
    public function __construct(
        private FeatureGate $features,
        private UnitConverter $converter,
        private EnsureBranchStock $ensureBranchStock,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: mixed}>  $items
     */
    public function handle(int $branchId, array $items): void
    {
        if (! $this->features->allows('module.inventory')) {
            return;
        }

        if (! $this->blockOnStockout($branchId)) {
            return;
        }

        $needed = $this->requiredQuantities($branchId, $items);

        foreach ($needed as $stockItemId => $quantity) {
            $this->ensureBranchStock->handle($branchId, $stockItemId);
            $onHand = (string) BranchStock::query()
                ->where('branch_id', $branchId)
                ->where('stock_item_id', $stockItemId)
                ->value('quantity');

            if (bccomp($onHand, $quantity, 3) === -1) {
                throw ValidationException::withMessages([
                    'items' => __('app.stockout_blocked'),
                    'stock_item_id' => (string) $stockItemId,
                    'required' => $quantity,
                    'available' => number_format((float) $onHand, 3, '.', ''),
                ]);
            }
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: mixed}>  $items
     * @return array<int, string> stock_item_id => quantity in stock item unit
     */
    public function requiredQuantities(int $branchId, array $items): array
    {
        $needed = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $orderQty = number_format((float) $item['quantity'], 3, '.', '');
            $recipe = Recipe::query()
                ->where('product_id', $productId)
                ->with(['lines.stockItem'])
                ->first();

            if ($recipe === null) {
                continue;
            }

            $yield = bccomp((string) $recipe->yield_qty, '0', 3) === 1
                ? (string) $recipe->yield_qty
                : '1.000';

            foreach ($recipe->lines as $line) {
                $lineQty = bcmul((string) $line->quantity, $orderQty, 8);
                $lineQty = bcdiv($lineQty, $yield, 8);
                $inStockUnit = $this->converter->convert(
                    number_format((float) $lineQty, 6, '.', ''),
                    (int) $line->unit_id,
                    (int) $line->stockItem->unit_id,
                );

                $stockItemId = (int) $line->stock_item_id;
                $needed[$stockItemId] = isset($needed[$stockItemId])
                    ? number_format((float) bcadd($needed[$stockItemId], $inStockUnit, 4), 3, '.', '')
                    : $inStockUnit;
            }
        }

        return $needed;
    }

    public function blockOnStockout(int $branchId): bool
    {
        $raw = DB::table('branch_settings')
            ->where('branch_id', $branchId)
            ->where('key', 'inventory.block_on_stockout')
            ->value('value');

        if ($raw === null) {
            return false;
        }

        return (bool) json_decode((string) $raw, true);
    }
}
