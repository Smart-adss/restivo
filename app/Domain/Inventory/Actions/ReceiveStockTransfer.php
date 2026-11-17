<?php

namespace App\Domain\Inventory\Actions;

use App\Models\StockTransfer;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReceiveStockTransfer
{
    public function __construct(
        private FeatureGate $features,
        private RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.movements.adjust'), 403);
        abort_unless($this->features->allows('module.inventory'), 404);
        abort_unless($this->features->allows('module.multi_branch'), 404);

        if ($transfer->status === 'received') {
            throw new HttpException(409, __('app.stock_transfer_already_received'));
        }

        if ($transfer->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => __('app.stock_transfer_not_sent'),
            ]);
        }

        return DB::transaction(function () use ($transfer): StockTransfer {
            $locked = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'received') {
                throw new HttpException(409, __('app.stock_transfer_already_received'));
            }

            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages([
                    'status' => __('app.stock_transfer_not_sent'),
                ]);
            }

            $locked->load('lines');

            foreach ($locked->lines as $line) {
                $this->recordStockMovement->handle([
                    'branch_id' => $locked->to_branch_id,
                    'stock_item_id' => (int) $line->stock_item_id,
                    'kind' => 'transfer_in',
                    'quantity' => (string) $line->quantity,
                    'note' => 'Transfer in #'.$locked->public_id,
                    'idempotency_key' => 'transfer-in:'.$locked->public_id.':'.$line->stock_item_id,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $locked->id,
                ], system: true);
            }

            $locked->status = 'received';
            $locked->received_at = now();
            $locked->save();

            return $locked->fresh(['lines.stockItem']);
        });
    }
}
