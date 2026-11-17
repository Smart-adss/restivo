<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\BuildBranchComparisonReport;
use App\Domain\Finance\Actions\BuildEstimatedProfitReport;
use App\Domain\Finance\Actions\BuildShiftCashReport;
use App\Domain\Finance\Actions\BuildTaxReport;
use App\Domain\Finance\Actions\ExportExpensesCsv;
use App\Domain\Finance\Actions\ExportOrdersCsv;
use App\Support\Features\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function tax(Request $request, BuildTaxReport $report): View
    {
        abort_unless(auth()->user()->hasPermission('finance.reports.advanced'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id'));
        $from = (string) $request->query('from', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->toDateString());

        $result = $report->handle($branch, $from, $to);

        return view('app.reports.tax', [
            'branches' => $branches,
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'report' => $result,
        ]);
    }

    public function shifts(Request $request, BuildShiftCashReport $report): View
    {
        abort_unless(auth()->user()->hasPermission('finance.reports.view'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $from = $request->query('from');
        $to = $request->query('to');

        $rows = $report->handle(
            $branch,
            is_string($from) && $from !== '' ? $from : null,
            is_string($to) && $to !== '' ? $to : null,
        );

        return view('app.reports.shifts', [
            'branches' => $branches,
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }

    public function profit(Request $request, BuildEstimatedProfitReport $report, FeatureGate $features): View
    {
        abort_unless(auth()->user()->hasPermission('finance.reports.advanced'), 403);
        abort_unless($features->allows('module.advanced_reports'), 404);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $from = (string) $request->query('from', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->toDateString());

        return view('app.reports.profit', [
            'branches' => $branches,
            'branch' => $branch,
            'from' => $from,
            'to' => $to,
            'report' => $report->handle($branch, $from, $to),
        ]);
    }

    public function branches(Request $request, BuildBranchComparisonReport $report): View
    {
        $from = (string) $request->query('from', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->startOfMonth()->toDateString());
        $to = (string) $request->query('to', now(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->toDateString());

        return view('app.reports.branches', [
            'from' => $from,
            'to' => $to,
            'rows' => $report->handle($from, $to),
        ]);
    }

    public function exportOrders(Request $request, ExportOrdersCsv $export): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('finance.reports.view'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id'));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        return $export->handle($branch, $from, $to);
    }

    public function exportExpenses(Request $request, ExportExpensesCsv $export): StreamedResponse
    {
        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id'));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        return $export->handle($branch, $from, $to);
    }
}
