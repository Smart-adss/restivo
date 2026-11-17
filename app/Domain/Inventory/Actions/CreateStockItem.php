<?php

namespace App\Domain\Inventory\Actions;

use App\Models\StockItem;
use App\Models\Unit;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateStockItem
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, unit_id: int, kind: string, sku?: ?string}  $data
     */
    public function handle(array $data): StockItem
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.items.manage'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        if (! in_array($data['kind'], ['ingredient', 'packaging', 'resale'], true)) {
            throw ValidationException::withMessages([
                'kind' => __('app.stock_item_kind_invalid'),
            ]);
        }

        $unit = Unit::query()->find($data['unit_id']);

        if ($unit === null) {
            throw ValidationException::withMessages([
                'unit_id' => __('app.unit_unavailable'),
            ]);
        }

        $sku = $data['sku'] ?? null;

        if ($sku !== null && $sku !== '' && StockItem::query()->where('sku', $sku)->exists()) {
            throw ValidationException::withMessages([
                'sku' => __('app.stock_item_sku_taken'),
            ]);
        }

        return StockItem::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'sku' => $sku ?: null,
            'unit_id' => $unit->id,
            'kind' => $data['kind'],
            'is_active' => true,
        ]);
    }
}
