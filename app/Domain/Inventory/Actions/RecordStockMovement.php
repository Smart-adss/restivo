<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovement
{
    public function __construct(
        private FeatureGate $features,
        private EnsureBranchStock $ensureBranchStock,
    ) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   stock_item_id: int,
     *   kind: string,
     *   quantity: string|float|int,
     *   note?: ?string,
     *   unit_cost?: ?string,
     *   idempotency_key?: ?string,
     *   reference_type?: ?string,
     *   reference_id?: ?int
     * }  $data
     */
    public function handle(array $data): StockMovement
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.movements.adjust'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        $kind = $data['kind'];
        $allowed = ['purchase', 'sale', 'adjustment', 'transfer_in', 'transfer_out', 'waste', 'count'];

        if (! in_array($kind, $allowed, true)) {
            throw ValidationException::withMessages([
                'kind' => __('app.stock_movement_kind_invalid'),
            ]);
        }

        $rawQuantity = (string) $data['quantity'];

        if (! is_numeric($rawQuantity) || bccomp($rawQuantity, '0', 3) === 0) {
            throw ValidationException::withMessages([
                'quantity' => __('app.stock_movement_quantity_invalid'),
            ]);
        }

        $quantity = number_format((float) $rawQuantity, 3, '.', '');

        if ($kind === 'waste') {
            if (bccomp($quantity, '0', 3) !== 1) {
                throw ValidationException::withMessages([
                    'quantity' => __('app.stock_waste_quantity_positive'),
                ]);
            }

            $quantity = number_format(-1 * abs((float) $quantity), 3, '.', '');
            $note = trim((string) ($data['note'] ?? ''));

            if ($note === '') {
                throw ValidationException::withMessages([
                    'note' => __('app.stock_waste_note_required'),
                ]);
            }
        }

        if ($kind === 'adjustment') {
            $note = trim((string) ($data['note'] ?? ''));

            if ($note === '') {
                throw ValidationException::withMessages([
                    'note' => __('app.stock_adjustment_note_required'),
                ]);
            }
        }

        $branch = Branch::query()->find($data['branch_id']);
        $item = StockItem::query()->find($data['stock_item_id']);

        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        if ($item === null || ! $item->is_active) {
            throw ValidationException::withMessages([
                'stock_item_id' => __('app.stock_item_unavailable'),
            ]);
        }

        $key = $data['idempotency_key'] ?? null;

        if ($key !== null && $key !== '') {
            $existing = StockMovement::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data, $branch, $item, $kind, $quantity, $key): StockMovement {
            if ($key !== null && $key !== '') {
                $existing = StockMovement::query()
                    ->where('idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $this->ensureBranchStock->handle($branch->id, $item->id);

            $stock = BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $movement = StockMovement::query()->create([
                'branch_id' => $branch->id,
                'stock_item_id' => $item->id,
                'kind' => $kind,
                'quantity' => $quantity,
                'unit_cost' => $data['unit_cost'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'idempotency_key' => $key ?: null,
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            $newQty = number_format((float) bcadd((string) $stock->quantity, $quantity, 4), 3, '.', '');

            BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->update([
                    'quantity' => $newQty,
                    'updated_at' => now(),
                ]);

            return $movement;
        });
    }
}
