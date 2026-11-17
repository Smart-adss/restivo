<?php

namespace App\Domain\Tables\Actions;

use App\Models\Branch;
use App\Models\DiningArea;
use App\Models\DiningTable;
use App\Support\Features\FeatureGate;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateDiningTable
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{branch_id: int, area_id?: ?int, name: string, seats: int}  $data
     */
    public function handle(array $data): DiningTable
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('tables.layout.manage'), 403);

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null || ! auth()->user()->canAccessBranch($branch)) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $areaId = $data['area_id'] ?? null;

        if ($areaId !== null) {
            $area = DiningArea::query()
                ->whereKey($areaId)
                ->where('branch_id', $branch->id)
                ->first();

            if ($area === null) {
                throw ValidationException::withMessages([
                    'area_id' => __('app.dining_area_unavailable'),
                ]);
            }
        }

        if (DiningTable::query()->where('branch_id', $branch->id)->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => __('app.dining_table_name_taken'),
            ]);
        }

        try {
            return DiningTable::query()->create([
                'branch_id' => $branch->id,
                'area_id' => $areaId,
                'name' => $data['name'],
                'seats' => $data['seats'],
                'status' => 'available',
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages([
                    'name' => __('app.dining_table_name_taken'),
                ]);
            }

            throw $exception;
        }
    }
}
