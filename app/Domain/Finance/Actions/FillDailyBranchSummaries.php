<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\DailyBranchSummary;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;

class FillDailyBranchSummaries
{
    public function __construct(private BuildDailyBranchReport $report) {}

    public function handle(?Carbon $reference = null): int
    {
        $reference ??= now('UTC');
        $written = 0;

        $tenants = Tenant::query()->where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant->id);
            $timezone = $tenant->timezone ?: 'Africa/Cairo';
            $local = $reference->copy()->timezone($timezone);
            $dates = [
                $local->copy()->subDay()->toDateString(),
                $local->toDateString(),
            ];

            $branches = Branch::query()->where('is_active', true)->get();

            foreach ($branches as $branch) {
                foreach (array_values(array_unique($dates)) as $date) {
                    $summary = $this->report->handle($branch, $date);

                    $row = DailyBranchSummary::query()
                        ->where('branch_id', $branch->id)
                        ->whereDate('business_date', $date)
                        ->first();

                    $payload = [
                        'orders_count' => $summary['orders_count'],
                        'gross_total' => $summary['gross_total'],
                        'discount_total' => $summary['discount_total'],
                        'tax_total' => $summary['tax_total'],
                        'net_total' => $summary['net_total'],
                        'cash_total' => $summary['cash_total'],
                        'electronic_total' => $summary['electronic_total'],
                        'refund_total' => $summary['refund_total'],
                        'delivery_fee_total' => $summary['delivery_fee_total'],
                        'calculated_at' => now(),
                    ];

                    if ($row !== null) {
                        $row->update($payload);
                    } else {
                        DailyBranchSummary::query()->create($payload + [
                            'branch_id' => $branch->id,
                            'business_date' => $date,
                        ]);
                    }

                    $written++;
                }
            }
        }

        return $written;
    }
}
