<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockTransfer
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   from_branch_id: int,
     *   to_branch_id: int,
     *   lines: list<array{stock_item_id: int, quantity: mixed}>
     * }  $data
     */
    public function handle(array $data): StockTransfer
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.movements.adjust'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);
        abort_unless($this->features->allows('module.multi_branch'), 404);

        $from = Branch::query()->find($data['from_branch_id']);
        $to = Branch::query()->find($data['to_branch_id']);

        if ($from === null || ! auth()->user()->canAccessBranch($from)) {
            throw ValidationException::withMessages([
                'from_branch_id' => __('app.branch_unavailable'),
            ]);
        }

        if ($to === null || ! auth()->user()->canAccessBranch($to)) {
            throw ValidationException::withMessages([
                'to_branch_id' => __('app.branch_unavailable'),
            ]);
        }

        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages([
                'to_branch_id' => __('app.stock_transfer_same_branch'),
            ]);
        }

        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('app.stock_transfer_lines_required'),
            ]);
        }

        return DB::transaction(function () use ($from, $to, $lines): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => 'draft',
            ]);

            $seen = [];

            foreach ($lines as $index => $line) {
                $stockItemId = (int) $line['stock_item_id'];
                $quantity = number_format((float) $line['quantity'], 3, '.', '');

                if (isset($seen[$stockItemId])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_transfer_line_duplicate'),
                    ]);
                }
                $seen[$stockItemId] = true;

                if (bccomp($quantity, '0', 3) !== 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => __('app.stock_transfer_quantity_invalid'),
                    ]);
                }

                if (! StockItem::query()->whereKey($stockItemId)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_item_unavailable'),
                    ]);
                }

                StockTransferLine::query()->create([
                    'transfer_id' => $transfer->id,
                    'stock_item_id' => $stockItemId,
                    'quantity' => $quantity,
                ]);
            }

            return $transfer->fresh(['lines.stockItem', 'fromBranch', 'toBranch']);
        });
    }
}
