<?php

namespace App\Domain\Inventory\Actions;

use App\Models\BranchStock;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivePurchaseOrder
{
    public function __construct(
        private FeatureGate $features,
        private RecordStockMovement $recordStockMovement,
        private EnsureBranchStock $ensureBranchStock,
    ) {}

    /**
     * @param  list<array{line_id: int, quantity: mixed}>  $lines
     */
    public function handle(PurchaseOrder $order, array $lines): GoodsReceipt
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('purchasing.orders.manage'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);

        if (! in_array($order->status, ['draft', 'sent', 'partial'], true)) {
            throw ValidationException::withMessages([
                'status' => __('app.purchase_order_not_receivable'),
            ]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('app.purchase_receive_lines_required'),
            ]);
        }

        return DB::transaction(function () use ($order, $lines): GoodsReceipt {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->load('lines');

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'received_at' => now(),
                'user_id' => auth()->id(),
            ]);

            foreach ($lines as $index => $input) {
                $lineId = (int) $input['line_id'];
                $qty = number_format((float) $input['quantity'], 3, '.', '');

                if (bccomp($qty, '0', 3) !== 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => __('app.purchase_receive_quantity_invalid'),
                    ]);
                }

                $poLine = $locked->lines->firstWhere('id', $lineId);
                if ($poLine === null) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.line_id" => __('app.purchase_order_line_unavailable'),
                    ]);
                }

                $remaining = $poLine->remainingQty();
                if (bccomp($qty, $remaining, 3) === 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => __('app.purchase_receive_exceeds_remaining'),
                    ]);
                }

                GoodsReceiptLine::query()->create([
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $poLine->id,
                    'quantity' => $qty,
                    'unit_cost' => $poLine->unit_cost,
                ]);

                $this->ensureBranchStock->handle($locked->branch_id, (int) $poLine->stock_item_id);

                $stock = BranchStock::query()
                    ->where('branch_id', $locked->branch_id)
                    ->where('stock_item_id', $poLine->stock_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $oldQty = (string) $stock->quantity;
                $oldAvg = $stock->avg_cost !== null ? (string) $stock->avg_cost : '0.0000';
                $unitCost = number_format((float) $poLine->unit_cost, 4, '.', '');

                if (bccomp($oldQty, '0', 3) !== 1) {
                    $newAvg = $unitCost;
                } else {
                    $numerator = bcadd(bcmul($oldQty, $oldAvg, 8), bcmul($qty, $unitCost, 8), 8);
                    $denominator = bcadd($oldQty, $qty, 8);
                    $newAvg = number_format((float) bcdiv($numerator, $denominator, 8), 4, '.', '');
                }

                $this->recordStockMovement->handle([
                    'branch_id' => $locked->branch_id,
                    'stock_item_id' => (int) $poLine->stock_item_id,
                    'kind' => 'purchase',
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'note' => 'PO receive #'.$locked->public_id,
                    'idempotency_key' => 'po-receive:'.$receipt->public_id.':'.$poLine->id,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                ], system: true);

                BranchStock::query()
                    ->where('branch_id', $locked->branch_id)
                    ->where('stock_item_id', $poLine->stock_item_id)
                    ->update([
                        'avg_cost' => $newAvg,
                        'updated_at' => now(),
                    ]);

                PurchaseOrderLine::query()->whereKey($poLine->id)->update([
                    'received_qty' => number_format((float) bcadd((string) $poLine->received_qty, $qty, 4), 3, '.', ''),
                    'updated_at' => now(),
                ]);
            }

            $locked->refresh()->load('lines');
            $allReceived = $locked->lines->every(
                fn (PurchaseOrderLine $line) => bccomp((string) $line->received_qty, (string) $line->quantity, 3) !== -1
            );
            $anyReceived = $locked->lines->contains(
                fn (PurchaseOrderLine $line) => bccomp((string) $line->received_qty, '0', 3) === 1
            );

            $locked->status = $allReceived ? 'received' : ($anyReceived ? 'partial' : $locked->status);
            if ($locked->ordered_at === null) {
                $locked->ordered_at = now();
            }
            $locked->save();

            return $receipt->fresh(['lines']);
        });
    }
}
