<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Kitchen\Actions\CreateKitchenTickets;
use App\Domain\Ordering\OrderStatusMachine;
use App\Jobs\ConsumeOrderRecipesJob;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TransitionOrderStatus
{
    public function __construct(
        private FeatureGate $features,
        private CreateKitchenTickets $createKitchenTickets,
    ) {}

    public function handle(Order $order, string $to, ?int $userId = null): Order
    {
        $this->features->assertWritable();

        $result = DB::transaction(function () use ($order, $to, $userId): Order {
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

            if ($to === OrderStatusMachine::ACCEPTED && $locked->source !== 'pos') {
                $this->createKitchenTickets->handle($locked);
            }

            return $locked->fresh(['items.options', 'discounts']);
        });

        if ($to === OrderStatusMachine::COMPLETED && $this->features->allows('module.inventory')) {
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                try {
                    ConsumeOrderRecipesJob::dispatch($result->id, $tenantId);
                } catch (\Throwable $exception) {
                    report($exception);
                    ConsumeOrderRecipesJob::recordSyncFailure($result->id, $tenantId, $exception);
                }
            }
        }

        return $result;
    }
}
