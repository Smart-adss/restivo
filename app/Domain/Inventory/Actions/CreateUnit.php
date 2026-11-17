<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Unit;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateUnit
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, abbreviation: string, kind: string}  $data
     */
    public function handle(array $data): Unit
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.items.manage'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        if (! in_array($data['kind'], ['weight', 'volume', 'count'], true)) {
            throw ValidationException::withMessages([
                'kind' => __('app.unit_kind_invalid'),
            ]);
        }

        return Unit::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'abbreviation' => $data['abbreviation'],
            'kind' => $data['kind'],
        ]);
    }
}
