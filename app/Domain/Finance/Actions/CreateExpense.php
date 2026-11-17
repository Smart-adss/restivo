<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   category_id: int,
     *   amount: mixed,
     *   tax_amount?: mixed,
     *   incurred_on: string,
     *   note?: ?string
     * }  $data
     */
    public function handle(array $data): Expense
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('finance.expenses.manage'), 403);

        $branch = Branch::query()->find($data['branch_id']);
        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $category = ExpenseCategory::query()
            ->whereKey($data['category_id'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => __('app.expense_category_unavailable'),
            ]);
        }

        if (empty($data['incurred_on'])) {
            throw ValidationException::withMessages([
                'incurred_on' => __('app.expense_date_required'),
            ]);
        }

        $amount = Money::of($data['amount'] ?? '');
        if (Money::compare($amount, '0') !== 1) {
            throw ValidationException::withMessages([
                'amount' => __('app.expense_amount_required'),
            ]);
        }

        $tax = Money::of($data['tax_amount'] ?? '0');
        if (Money::compare($tax, '0') === -1) {
            throw ValidationException::withMessages([
                'tax_amount' => __('app.expense_tax_invalid'),
            ]);
        }

        return Expense::query()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'tax_amount' => $tax,
            'incurred_on' => $data['incurred_on'],
            'note' => $data['note'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }
}
