<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Subscription\AuditLogger;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportOrdersCsv
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(?Branch $branch, string $from, string $to): StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('orders.orders.view'), 403);
        abort_unless(auth()->user()?->hasPermission('finance.reports.view'), 403);

        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        $timezone = $this->timezone();
        $start = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $end = Carbon::parse($to, $timezone)->endOfDay()->utc();

        $query = Order::query()
            ->with('branch')
            ->whereBetween('placed_at', [$start, $end])
            ->orderBy('placed_at');

        if ($branch !== null) {
            $query->where('branch_id', $branch->id);
        } else {
            $branchIds = auth()->user()->visibleBranches()->pluck('id');
            $query->whereIn('branch_id', $branchIds);
        }

        $orders = $query->get();

        $this->audit->log('export.orders_csv', null, null, [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'rows' => $orders->count(),
        ]);

        $filename = 'orders-'.$from.'-'.$to.'.csv';

        return Response::streamDownload(function () use ($orders, $timezone): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'public_id', 'branch', 'status', 'source', 'subtotal', 'tax_total', 'discount_total', 'total', 'placed_at']);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->number,
                    $order->public_id,
                    $order->branch?->name() ?? $order->branch_id,
                    $order->status,
                    $order->source,
                    (string) $order->subtotal,
                    (string) $order->tax_total,
                    (string) $order->discount_total,
                    (string) $order->total,
                    Carbon::parse($order->placed_at)->timezone($timezone)->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
