<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Support\UnitConverter;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;

class ConsumeOrderRecipes
{
    public function __construct(
        private FeatureGate $features,
        private UnitConverter $converter,
        private RecordStockMovement $recordStockMovement,
    ) {}

    /**
     * @return list<StockMovement>
     */
    public function handle(Order $order): array
    {
        if (! $this->features->allows('module.inventory')) {
            return [];
        }

        $order->loadMissing(['items']);

        $movements = [];
        $aggregated = [];

        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $recipe = Recipe::query()
                ->where('product_id', $item->product_id)
                ->with(['lines.stockItem'])
                ->first();

            if ($recipe === null) {
                continue;
            }

            $orderQty = number_format((float) $item->quantity, 3, '.', '');
            $yield = bccomp((string) $recipe->yield_qty, '0', 3) === 1
                ? (string) $recipe->yield_qty
                : '1.000';

            foreach ($recipe->lines as $line) {
                $lineQty = bcdiv(bcmul((string) $line->quantity, $orderQty, 8), $yield, 8);
                $inStockUnit = $this->converter->convert(
                    number_format((float) $lineQty, 6, '.', ''),
                    (int) $line->unit_id,
                    (int) $line->stockItem->unit_id,
                );

                $stockItemId = (int) $line->stock_item_id;
                $aggregated[$stockItemId] = isset($aggregated[$stockItemId])
                    ? number_format((float) bcadd($aggregated[$stockItemId], $inStockUnit, 4), 3, '.', '')
                    : $inStockUnit;
            }
        }

        if ($aggregated === []) {
            return [];
        }

        return DB::transaction(function () use ($order, $aggregated, &$movements): array {
            foreach ($aggregated as $stockItemId => $quantity) {
                $key = 'order-consume:'.$order->public_id.':'.$stockItemId;

                $movement = $this->recordStockMovement->handle([
                    'branch_id' => $order->branch_id,
                    'stock_item_id' => $stockItemId,
                    'kind' => 'sale',
                    'quantity' => $quantity,
                    'note' => 'Recipe consume #'.$order->number,
                    'idempotency_key' => $key,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                ], system: true);

                $movements[] = $movement;
            }

            return $movements;
        });
    }
}
