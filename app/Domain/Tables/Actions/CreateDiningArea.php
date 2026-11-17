<?php

namespace App\Domain\Tables\Actions;

use App\Models\Branch;
use App\Models\DiningArea;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateDiningArea
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{branch_id: int, name_ar: string, name_en: string, sort_order?: int}  $data
     */
    public function handle(array $data): DiningArea
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.layout.manage'), 403);

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        return DiningArea::query()->create([
            'branch_id' => $branch->id,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }
}
