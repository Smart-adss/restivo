<?php

namespace App\Domain\Menu\Actions;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateOptionGroup
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *     name_ar: string,
     *     name_en: string,
     *     kind: string,
     *     selection: string,
     *     is_required?: bool,
     *     min_select?: int,
     *     max_select?: int,
     *     sort_order?: int
     * }  $data
     */
    public function handle(Product $product, array $data): ProductOptionGroup
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        if (! in_array($data['kind'], ProductOptionGroup::KINDS, true)) {
            throw ValidationException::withMessages([
                'kind' => __('app.option_kind_invalid'),
            ]);
        }

        if (! in_array($data['selection'], ProductOptionGroup::SELECTIONS, true)) {
            throw ValidationException::withMessages([
                'selection' => __('app.option_selection_invalid'),
            ]);
        }

        $min = (int) ($data['min_select'] ?? 0);
        $max = (int) ($data['max_select'] ?? ($data['selection'] === 'single' ? 1 : 1));

        if ($data['selection'] === 'single') {
            $max = 1;
        }

        if ($min > $max) {
            throw ValidationException::withMessages([
                'min_select' => __('app.option_select_bounds'),
            ]);
        }

        return ProductOptionGroup::query()->create([
            'product_id' => $product->id,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'kind' => $data['kind'],
            'selection' => $data['selection'],
            'is_required' => $data['is_required'] ?? false,
            'min_select' => $min,
            'max_select' => $max,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }
}
