<?php

namespace App\Domain\Inventory\Actions;

use App\Models\StockCount;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PostStockCount
{
    public function __construct(
        private FeatureGate $features,
        private RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(StockCount $count): StockCount
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.counts.manage'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);

        if ($count->status === 'posted') {
            throw new HttpException(409, __('app.stock_count_already_posted'));
        }

        if ($count->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => __('app.stock_count_not_draft'),
            ]);
        }

        return DB::transaction(function () use ($count): StockCount {
            $locked = StockCount::query()->whereKey($count->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'posted') {
                throw new HttpException(409, __('app.stock_count_already_posted'));
            }

            $locked->load('lines');

            foreach ($locked->lines as $line) {
                $diff = number_format((float) bcsub((string) $line->counted_qty, (string) $line->expected_qty, 4), 3, '.', '');

                if (bccomp($diff, '0', 3) === 0) {
                    continue;
                }

                $this->recordStockMovement->handle([
                    'branch_id' => $locked->branch_id,
                    'stock_item_id' => (int) $line->stock_item_id,
                    'kind' => 'count',
                    'quantity' => $diff,
                    'note' => 'Stock count #'.$locked->public_id,
                    'idempotency_key' => 'count-post:'.$locked->public_id.':'.$line->stock_item_id,
                    'reference_type' => StockCount::class,
                    'reference_id' => $locked->id,
                ], system: true);
            }

            $locked->status = 'posted';
            $locked->counted_at = now();
            $locked->save();

            return $locked->fresh(['lines.stockItem']);
        });
    }
}
