<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\OrderStatusMachine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransitionOrderStatus
{
    public function __construct(private FeatureGate $features) {}

    public function handle(Order $order, string $to, ?int $userId = null): Order
    {
        $this->features->assertWritable();

        return DB::transaction(function () use ($order, $to, $userId): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! OrderStatusMachine::canTransition($locked->status, $to, $locked->type)) {
                throw new HttpException(409, 'That status transition is not allowed.');
            }

            if ($to === OrderStatusMachine::CANCELLED) {
                throw new HttpException(409, 'Use the cancel action for cancellations.');
            }

            abort_unless(auth()->user()?->hasPermission('orders.orders.accept'), 403);

            $from = $locked->status;
            $locked->status = $to;

            if ($to === OrderStatusMachine::COMPLETED) {
                $locked->completed_at = now();
            }

            $locked->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            return $locked->fresh(['items.options', 'discounts']);
        });
    }
}
