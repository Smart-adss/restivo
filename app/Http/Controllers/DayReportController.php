<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\BuildDailyBranchReport;
use App\Models\DailyBranchSummary;
use App\Support\Features\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DayReportController extends Controller
{
    public function show(Request $request, BuildDailyBranchReport $report, FeatureGate $features): View
    {
        abort_unless(auth()->user()->hasPermission('finance.reports.view'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $date = (string) $request->query('date', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->toDateString());

        $live = $report->handle($branch, $date);
        $stored = DailyBranchSummary::query()
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', $date)
            ->first();

        return view('app.reports.day', [
            'branches' => $branches,
            'branch' => $branch,
            'date' => $date,
            'live' => $live,
            'stored' => $stored,
        ]);
    }
}
