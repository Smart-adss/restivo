<?php

namespace App\Domain\Finance\Actions;

use App\Models\DailyBranchSummary;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

class BuildBranchComparisonReport
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @return list<array{
     *   branch_id: int,
     *   branch_name: string,
     *   orders_count: int,
     *   gross_total: string,
     *   tax_total: string,
     *   net_total: string,
     *   cash_total: string,
     *   electronic_total: string,
     *   refund_total: string
     * }>
     */
    public function handle(string $from, string $to): array
    {
        abort_unless($this->features->allows('module.multi_branch'), 404);
        abort_unless($this->features->allows('module.advanced_reports'), 404);
        abort_unless(auth()->user()?->hasPermission('finance.reports.advanced'), 403);

        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        $branchIds = auth()->user()->visibleBranches()->pluck('id');

        $rows = DailyBranchSummary::query()
            ->with('branch')
            ->whereIn('branch_id', $branchIds)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->get()
            ->groupBy('branch_id');

        $result = [];

        foreach ($rows as $branchId => $summaries) {
            $orders = 0;
            $gross = '0.00';
            $tax = '0.00';
            $net = '0.00';
            $cash = '0.00';
            $electronic = '0.00';
            $refund = '0.00';

            foreach ($summaries as $summary) {
                $orders += (int) $summary->orders_count;
                $gross = Money::add($gross, (string) $summary->gross_total);
                $tax = Money::add($tax, (string) $summary->tax_total);
                $net = Money::add($net, (string) $summary->net_total);
                $cash = Money::add($cash, (string) $summary->cash_total);
                $electronic = Money::add($electronic, (string) $summary->electronic_total);
                $refund = Money::add($refund, (string) $summary->refund_total);
            }

            $branch = $summaries->first()->branch;

            $result[] = [
                'branch_id' => (int) $branchId,
                'branch_name' => $branch?->name() ?? (string) $branchId,
                'orders_count' => $orders,
                'gross_total' => $gross,
                'tax_total' => $tax,
                'net_total' => $net,
                'cash_total' => $cash,
                'electronic_total' => $electronic,
                'refund_total' => $refund,
            ];
        }

        usort($result, fn (array $a, array $b): int => strcmp($a['branch_name'], $b['branch_name']));

        return $result;
    }
}
