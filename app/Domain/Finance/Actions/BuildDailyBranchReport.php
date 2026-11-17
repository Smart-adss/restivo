<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BuildDailyBranchReport
{
    /**
     * @return array{
     *   business_date: string,
     *   orders_count: int,
     *   gross_total: string,
     *   discount_total: string,
     *   tax_total: string,
     *   net_total: string,
     *   cash_total: string,
     *   electronic_total: string,
     *   refund_total: string,
     *   delivery_fee_total: string
     * }
     */
    public function handle(Branch $branch, string $businessDate): array
    {
        $timezone = $this->timezone();
        $start = Carbon::parse($businessDate, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($businessDate, $timezone)->endOfDay()->utc();

        $orders = Order::query()
            ->select([
                'id',
                'subtotal',
                'discount_total',
                'tax_total',
                'delivery_fee',
                'total',
                'status',
            ])
            ->where('branch_id', $branch->id)
            ->whereBetween('placed_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->get();

        $gross = '0.00';
        $discount = '0.00';
        $tax = '0.00';
        $delivery = '0.00';

        foreach ($orders as $order) {
            $gross = Money::add($gross, (string) $order->subtotal);
            $discount = Money::add($discount, (string) $order->discount_total);
            $tax = Money::add($tax, (string) $order->tax_total);
            $delivery = Money::add($delivery, (string) $order->delivery_fee);
        }

        $orderIds = $orders->pluck('id');

        $cash = '0.00';
        $electronic = '0.00';

        if ($orderIds->isNotEmpty()) {
            $payments = Payment::query()
                ->whereIn('order_id', $orderIds)
                ->whereIn('status', ['paid', 'refunded'])
                ->get();

            foreach ($payments as $payment) {
                if ($payment->method === 'cash') {
                    $cash = Money::add($cash, (string) $payment->amount);
                } else {
                    $electronic = Money::add($electronic, (string) $payment->amount);
                }
            }
        }

        $refundTotal = '0.00';

        if ($orderIds->isNotEmpty()) {
            $refunds = Refund::query()
                ->whereIn('order_id', $orderIds)
                ->where('status', 'completed')
                ->get();

            foreach ($refunds as $refund) {
                $refundTotal = Money::add($refundTotal, (string) $refund->amount);
            }
        }

        $net = Money::sub(Money::sub($gross, $discount), $refundTotal);

        return [
            'business_date' => $businessDate,
            'orders_count' => $orders->count(),
            'gross_total' => $gross,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'net_total' => $net,
            'cash_total' => $cash,
            'electronic_total' => $electronic,
            'refund_total' => $refundTotal,
            'delivery_fee_total' => $delivery,
        ];
    }

    private function timezone(): string
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return 'Africa/Cairo';
        }

        return (string) (Tenant::query()->whereKey($tenantId)->value('timezone') ?: 'Africa/Cairo');
    }
}
