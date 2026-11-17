<?php

namespace App\Http\Controllers;

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
