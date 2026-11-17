<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\OrderStatusMachine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CancelOrder
{
    public function __construct(private FeatureGate $features) {}

    public function handle(Order $order, ?int $userId = null): Order
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('orders.orders.cancel'), 403);

        return DB::transaction(function () use ($order, $userId): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (OrderStatusMachine::isTerminal($locked->status)) {
                throw new HttpException(409, 'The order is already closed.');
            }

            $needsElevatedCancel = ! in_array($locked->status, [
                OrderStatusMachine::PLACED,
                OrderStatusMachine::ACCEPTED,
            ], true);

            if ($needsElevatedCancel && ! auth()->user()?->hasPermission('orders.orders.cancel')) {
                throw new HttpException(403, 'Cancelling after preparation requires cancel permission.');
            }

            $from = $locked->status;
            $locked->status = OrderStatusMachine::CANCELLED;
            $locked->cancelled_at = now();
            $locked->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => OrderStatusMachine::CANCELLED,
                'user_id' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            return $locked->fresh(['items.options', 'discounts']);
        });
    }
}
