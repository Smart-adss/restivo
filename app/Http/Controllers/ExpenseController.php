<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Actions\CreateExpense;
use App\Domain\Finance\Actions\CreateExpenseCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request, FeatureGate $features): View
    {
        abort_unless(auth()->user()->hasPermission('finance.expenses.manage'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();

        $expenses = Expense::query()
            ->with(['category', 'branch'])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->latest('incurred_on')
            ->latest('id')
            ->limit(50)
            ->get();

        return view('app.expenses.index', [
            'branches' => $branches,
            'branch' => $branch,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'expenses' => $expenses,
        ]);
    }

    public function storeCategory(Request $request, CreateExpenseCategory $create, FeatureGate $features): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('finance.expenses.manage'), 403);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
        ]);

        try {
            $create->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.expense_category_created'));
    }

    public function store(Request $request, CreateExpense $create, FeatureGate $features): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('finance.expenses.manage'), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'incurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $create->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.expenses.index', ['branch_id' => $data['branch_id']])
            ->with('status', __('app.expense_created'));
    }
}
