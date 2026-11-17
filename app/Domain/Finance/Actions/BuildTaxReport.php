<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BuildTaxReport
{
    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   branch_id: int|null,
     *   orders_count: int,
     *   tax_total: string,
     *   gross_total: string,
     *   rows: list<array{business_date: string, orders_count: int, tax_total: string, gross_total: string}>
     * }
     */
    public function handle(?Branch $branch, string $from, string $to): array
    {
        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        $timezone = $this->timezone();
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'to' => __('app.report_date_range_invalid'),
            ]);
        }

        $query = Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->where('status', '!=', 'cancelled');

        if ($branch !== null) {
            $query->where('branch_id', $branch->id);
        }

        $orders = $query->orderBy('placed_at')->get();

        $tax = '0.00';
        $gross = '0.00';
        $byDate = [];

        foreach ($orders as $order) {
            $tax = Money::add($tax, (string) $order->tax_total);
            $gross = Money::add($gross, (string) $order->subtotal);

            $businessDate = Carbon::parse($order->placed_at)->timezone($timezone)->toDateString();
            if (! isset($byDate[$businessDate])) {
                $byDate[$businessDate] = [
                    'business_date' => $businessDate,
                    'orders_count' => 0,
                    'tax_total' => '0.00',
                    'gross_total' => '0.00',
                ];
            }

            $byDate[$businessDate]['orders_count']++;
            $byDate[$businessDate]['tax_total'] = Money::add($byDate[$businessDate]['tax_total'], (string) $order->tax_total);
            $byDate[$businessDate]['gross_total'] = Money::add($byDate[$businessDate]['gross_total'], (string) $order->subtotal);
        }

        ksort($byDate);

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'orders_count' => $orders->count(),
            'tax_total' => $tax,
            'gross_total' => $gross,
            'rows' => array_values($byDate),
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
