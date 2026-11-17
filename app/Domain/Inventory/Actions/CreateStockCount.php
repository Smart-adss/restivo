<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockCount
{
    public function __construct(
        private FeatureGate $features,
        private EnsureBranchStock $ensureBranchStock,
    ) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   lines: list<array{stock_item_id: int, counted_qty: mixed}>
     * }  $data
     */
    public function handle(array $data): StockCount
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.counts.manage'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);

        $branch = Branch::query()->find($data['branch_id']);
        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('app.stock_count_lines_required'),
            ]);
        }

        return DB::transaction(function () use ($data, $branch, $lines): StockCount {
            $count = StockCount::query()->create([
                'branch_id' => $branch->id,
                'status' => 'draft',
                'counted_at' => null,
                'user_id' => auth()->id(),
            ]);

            $seen = [];

            foreach ($lines as $index => $line) {
                $stockItemId = (int) $line['stock_item_id'];
                $counted = number_format((float) $line['counted_qty'], 3, '.', '');

                if (isset($seen[$stockItemId])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_count_line_duplicate'),
                    ]);
                }
                $seen[$stockItemId] = true;

                if (bccomp($counted, '0', 3) === -1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.counted_qty" => __('app.stock_count_quantity_invalid'),
                    ]);
                }

                if (! StockItem::query()->whereKey($stockItemId)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_item_unavailable'),
                    ]);
                }

                $this->ensureBranchStock->handle($branch->id, $stockItemId);
                $expected = (string) BranchStock::query()
                    ->where('branch_id', $branch->id)
                    ->where('stock_item_id', $stockItemId)
                    ->value('quantity');

                StockCountLine::query()->create([
                    'count_id' => $count->id,
                    'stock_item_id' => $stockItemId,
                    'expected_qty' => number_format((float) $expected, 3, '.', ''),
                    'counted_qty' => $counted,
                ]);
            }

            return $count->fresh(['lines.stockItem']);
        });
    }
}
