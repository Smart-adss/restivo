<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertPurchaseOrder
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   supplier_id: int,
     *   expected_at?: ?string,
     *   currency_code?: string,
     *   lines: list<array{stock_item_id: int, quantity: mixed, unit_cost: mixed}>
     * }  $data
     */
    public function handle(array $data, ?PurchaseOrder $existing = null): PurchaseOrder
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('purchasing.orders.manage'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);

        if ($existing !== null && $existing->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => __('app.purchase_order_not_draft'),
            ]);
        }

        $branch = Branch::query()->find($data['branch_id']);
        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $supplier = Supplier::query()->whereKey($data['supplier_id'])->where('is_active', true)->first();
        if ($supplier === null) {
            throw ValidationException::withMessages([
                'supplier_id' => __('app.supplier_unavailable'),
            ]);
        }

        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('app.purchase_order_lines_required'),
            ]);
        }

        return DB::transaction(function () use ($data, $existing, $branch, $supplier, $lines): PurchaseOrder {
            $subtotal = '0.00';
            $normalized = [];
            $seen = [];

            foreach ($lines as $index => $line) {
                $stockItemId = (int) $line['stock_item_id'];
                $quantity = number_format((float) $line['quantity'], 3, '.', '');
                $unitCost = number_format((float) $line['unit_cost'], 4, '.', '');

                if (isset($seen[$stockItemId])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.purchase_order_line_duplicate'),
                    ]);
                }
                $seen[$stockItemId] = true;

                if (bccomp($quantity, '0', 3) !== 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => __('app.purchase_order_line_quantity_invalid'),
                    ]);
                }

                if (bccomp($unitCost, '0', 4) === -1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_cost" => __('app.purchase_order_line_cost_invalid'),
                    ]);
                }

                if (! StockItem::query()->whereKey($stockItemId)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_item_unavailable'),
                    ]);
                }

                $lineTotal = number_format((float) bcmul($quantity, $unitCost, 6), 2, '.', '');
                $subtotal = Money::add($subtotal, $lineTotal);
                $normalized[] = [
                    'stock_item_id' => $stockItemId,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ];
            }

            $order = $existing ?? new PurchaseOrder;
            $order->fill([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'status' => 'draft',
                'expected_at' => $data['expected_at'] ?? null,
                'subtotal' => $subtotal,
                'tax_total' => '0.00',
                'total' => $subtotal,
                'currency_code' => $data['currency_code'] ?? 'EGP',
            ]);
            $order->save();

            PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->delete();

            foreach ($normalized as $line) {
                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $order->id,
                    'stock_item_id' => $line['stock_item_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'received_qty' => '0.000',
                ]);
            }

            return $order->fresh(['lines.stockItem', 'supplier', 'branch']);
        });
    }
}
