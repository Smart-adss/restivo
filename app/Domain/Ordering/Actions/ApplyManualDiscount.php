<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Subscription\AuditLogger;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApplyManualDiscount
{
    public function __construct(
        private FeatureGate $features,
        private AuditLogger $audit,
    ) {}

    public function handle(Order $order, string $amount, string $reason): OrderDiscount
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('orders.discounts.manual'), 403);

        $amount = Money::of($amount);
        $reason = trim($reason);

        if (Money::compare($amount, '0') !== 1) {
            throw ValidationException::withMessages([
                'amount' => __('app.manual_discount_amount_invalid'),
            ]);
        }

        if ($reason === '' || mb_strlen($reason) > 190) {
            throw ValidationException::withMessages([
                'reason' => __('app.manual_discount_reason_required'),
            ]);
        }

        return DB::transaction(function () use ($order, $amount, $reason): OrderDiscount {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['cancelled'], true)) {
                throw new HttpException(409, 'Cancelled orders cannot be discounted.');
            }

            if ($locked->payments()->where('status', 'paid')->exists()) {
                throw new HttpException(409, 'Paid orders cannot receive a manual discount.');
            }

            if (Money::compare($amount, (string) $locked->total) === 1) {
                throw ValidationException::withMessages([
                    'amount' => __('app.manual_discount_exceeds_total'),
                ]);
            }

            $before = [
                'discount_total' => (string) $locked->discount_total,
                'total' => (string) $locked->total,
            ];

            $discount = OrderDiscount::query()->create([
                'order_id' => $locked->id,
                'kind' => 'manual',
                'coupon_id' => null,
                'label' => $reason,
                'amount' => $amount,
            ]);

            $locked->update([
                'discount_total' => Money::add((string) $locked->discount_total, $amount),
                'total' => Money::sub((string) $locked->total, $amount),
            ]);

            $this->audit->log('order.manual_discount', $locked, $before, [
                'discount_total' => (string) $locked->fresh()->discount_total,
                'total' => (string) $locked->fresh()->total,
                'reason' => $reason,
                'amount' => $amount,
            ]);

            return $discount;
        });
    }
}
