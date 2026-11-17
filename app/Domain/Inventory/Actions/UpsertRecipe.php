<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\StockItem;
use App\Models\Unit;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertRecipe
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   yield_qty?: string|float|int,
     *   notes?: ?string,
     *   lines: list<array{stock_item_id: int, quantity: string|float|int, unit_id: int}>
     * }  $data
     */
    public function handle(Product $product, array $data): Recipe
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.items.manage'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        $lines = $data['lines'] ?? [];

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => __('app.recipe_lines_required'),
            ]);
        }

        return DB::transaction(function () use ($product, $data, $lines): Recipe {
            $recipe = Recipe::query()->firstOrNew(['product_id' => $product->id]);
            $recipe->fill([
                'yield_qty' => number_format((float) ($data['yield_qty'] ?? 1), 3, '.', ''),
                'notes' => $data['notes'] ?? null,
            ]);
            $recipe->save();

            RecipeLine::query()->where('recipe_id', $recipe->id)->delete();

            $seen = [];

            foreach ($lines as $index => $line) {
                $stockItemId = (int) $line['stock_item_id'];
                $unitId = (int) $line['unit_id'];
                $quantity = number_format((float) $line['quantity'], 3, '.', '');

                if (isset($seen[$stockItemId])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.recipe_line_duplicate'),
                    ]);
                }

                $seen[$stockItemId] = true;

                if (bccomp($quantity, '0', 3) !== 1) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => __('app.recipe_line_quantity_invalid'),
                    ]);
                }

                if (! StockItem::query()->whereKey($stockItemId)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.stock_item_id" => __('app.stock_item_unavailable'),
                    ]);
                }

                if (! Unit::query()->whereKey($unitId)->exists()) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.unit_id" => __('app.unit_unavailable'),
                    ]);
                }

                RecipeLine::query()->create([
                    'recipe_id' => $recipe->id,
                    'stock_item_id' => $stockItemId,
                    'quantity' => $quantity,
                    'unit_id' => $unitId,
                ]);
            }

            return $recipe->fresh(['lines.stockItem', 'lines.unit']);
        });
    }
}
