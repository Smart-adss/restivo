<?php

namespace App\Domain\Tables\Actions;

use App\Models\BillSplit;
use App\Models\BillSplitPart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SplitBill
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   mode: string,
     *   parts?: list<array{label?: string, amount?: string|float|int, order_item_ids?: list<int>}>,
     *   count?: int
     * }  $data
     */
    public function handle(Order $order, array $data): BillSplit
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.sessions.manage'), 403);

        $mode = $data['mode'] ?? '';

        if (! in_array($mode, ['equal', 'items', 'amount'], true)) {
            throw ValidationException::withMessages([
                'mode' => __('app.bill_split_mode_invalid'),
            ]);
        }

        return DB::transaction(function () use ($order, $data, $mode): BillSplit {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();
            $parts = $this->resolveParts($locked, $mode, $data);
            $sum = '0.00';

            foreach ($parts as $part) {
                $sum = Money::add($sum, $part['amount']);
            }

            if (Money::compare($sum, (string) $locked->total) !== 0) {
                throw ValidationException::withMessages([
                    'parts' => __('app.bill_split_total_mismatch'),
                    'expected_total' => (string) $locked->total,
                ]);
            }

            $split = BillSplit::query()->create([
                'order_id' => $locked->id,
                'mode' => $mode,
                'created_by' => auth()->id(),
            ]);

            foreach ($parts as $part) {
                BillSplitPart::query()->create([
                    'split_id' => $split->id,
                    'label' => $part['label'],
                    'amount' => $part['amount'],
                ]);
            }

            return $split->fresh(['parts']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, amount: string}>
     */
    private function resolveParts(Order $order, string $mode, array $data): array
    {
        if ($mode === 'equal') {
            $count = (int) ($data['count'] ?? count($data['parts'] ?? []));

            if ($count < 2) {
                throw new HttpException(422, 'Equal split requires at least two parts.');
            }

            $amounts = Money::split((string) $order->total, $count);
            $parts = [];

            foreach ($amounts as $index => $amount) {
                $parts[] = [
                    'label' => $data['parts'][$index]['label'] ?? __('app.bill_split_part', ['n' => $index + 1]),
                    'amount' => $amount,
                ];
            }

            return $parts;
        }

        if ($mode === 'amount') {
            $input = $data['parts'] ?? [];

            if (count($input) < 2) {
                throw new HttpException(422, 'Amount split requires at least two parts.');
            }

            $parts = [];

            foreach ($input as $index => $part) {
                $amount = Money::of($part['amount'] ?? 0);

                if (Money::compare($amount, '0') !== 1) {
                    throw ValidationException::withMessages([
                        "parts.{$index}.amount" => __('app.bill_split_amount_invalid'),
                    ]);
                }

                $parts[] = [
                    'label' => $part['label'] ?? __('app.bill_split_part', ['n' => $index + 1]),
                    'amount' => $amount,
                ];
            }

            return $parts;
        }

        $input = $data['parts'] ?? [];

        if (count($input) < 2) {
            throw new HttpException(422, 'Item split requires at least two parts.');
        }

        $claimed = [];
        $parts = [];

        foreach ($input as $index => $part) {
            $itemIds = $part['order_item_ids'] ?? [];

            if ($itemIds === []) {
                throw ValidationException::withMessages([
                    "parts.{$index}.order_item_ids" => __('app.bill_split_items_required'),
                ]);
            }

            $amount = '0.00';

            foreach ($itemIds as $itemId) {
                if (isset($claimed[$itemId])) {
                    throw ValidationException::withMessages([
                        "parts.{$index}.order_item_ids" => __('app.bill_split_item_claimed'),
                    ]);
                }

                $item = $order->items->firstWhere('id', (int) $itemId);

                if (! $item instanceof OrderItem) {
                    throw ValidationException::withMessages([
                        "parts.{$index}.order_item_ids" => __('app.bill_split_item_missing'),
                    ]);
                }

                $claimed[$itemId] = true;
                $amount = Money::add($amount, (string) $item->line_total);
            }

            $parts[] = [
                'label' => $part['label'] ?? __('app.bill_split_part', ['n' => $index + 1]),
                'amount' => $amount,
            ];
        }

        return $parts;
    }
}
