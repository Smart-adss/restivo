<?php

namespace App\Domain\Finance\Actions;

use App\Models\ExpenseCategory;
use App\Support\Features\FeatureGate;

class CreateExpenseCategory
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool}  $data
     */
    public function handle(array $data): ExpenseCategory
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('finance.expenses.manage'), 403);

        return ExpenseCategory::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
