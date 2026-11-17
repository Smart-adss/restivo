<?php

namespace App\Domain\Kitchen\Actions;

use App\Models\Branch;
use App\Models\PrepStation;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreatePrepStation
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{branch_id: int, name_ar: string, name_en: string, kind: string, sort_order?: int}  $data
     */
    public function handle(array $data): PrepStation
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tenant.settings.manage'), 403);

        if (! $this->features->allows('module.kitchen')) {
            abort(404);
        }

        if (! in_array($data['kind'], ['kitchen', 'bar', 'dessert', 'bakery'], true)) {
            throw ValidationException::withMessages([
                'kind' => __('app.prep_station_kind_invalid'),
            ]);
        }

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        return PrepStation::query()->create([
            'branch_id' => $branch->id,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'kind' => $data['kind'],
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }
}
