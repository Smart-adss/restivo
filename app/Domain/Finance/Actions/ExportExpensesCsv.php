<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Subscription\AuditLogger;
use App\Models\Branch;
use App\Models\Expense;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportExpensesCsv
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(?Branch $branch, string $from, string $to): StreamedResponse
    {
        abort_unless(auth()->user()?->hasPermission('finance.expenses.manage')
            || auth()->user()?->hasPermission('finance.reports.view'), 403);

        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        $query = Expense::query()
            ->with(['branch', 'category'])
            ->whereDate('incurred_on', '>=', $from)
            ->whereDate('incurred_on', '<=', $to)
            ->orderBy('incurred_on');

        if ($branch !== null) {
            $query->where('branch_id', $branch->id);
        } else {
            $branchIds = auth()->user()->visibleBranches()->pluck('id');
            $query->whereIn('branch_id', $branchIds);
        }

        $expenses = $query->get();

        $this->audit->log('export.expenses_csv', null, null, [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branch?->id,
            'rows' => $expenses->count(),
        ]);

        $filename = 'expenses-'.$from.'-'.$to.'.csv';

        return Response::streamDownload(function () use ($expenses): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['public_id', 'branch', 'category', 'amount', 'tax_amount', 'incurred_on', 'note']);

            foreach ($expenses as $expense) {
                fputcsv($out, [
                    $expense->public_id,
                    $expense->branch?->name() ?? $expense->branch_id,
                    $expense->category?->name() ?? $expense->category_id,
                    (string) $expense->amount,
                    (string) $expense->tax_amount,
                    $expense->incurred_on?->toDateString(),
                    $expense->note,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
