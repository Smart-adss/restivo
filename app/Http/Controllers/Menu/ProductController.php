<?php

namespace App\Http\Controllers\Menu;

use App\Domain\Inventory\Actions\UpsertRecipe;
use App\Domain\Menu\Actions\CreateOption;
use App\Domain\Menu\Actions\CreateOptionGroup;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Menu\Actions\StoreProductImage;
use App\Domain\Menu\Actions\UpdateProduct;
use App\Domain\Menu\Actions\UpsertOptionBranchSetting;
use App\Domain\Menu\Actions\UpsertProductBranchSetting;
use App\Models\StockItem;
use App\Models\Unit;
use App\Domain\Menu\Support\ConflictingDefaultOption;
use App\Domain\Menu\Support\InvalidProductImage;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\PrepStation;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(FeatureGate $features): View
    {
        $this->authorizeMenu($features);

        return view('app.menu.products.index', [
            'products' => Product::query()->with(['category', 'images'])->orderBy('name_ar')->get(),
        ]);
    }

    public function create(FeatureGate $features): View
    {
        $this->authorizeMenu($features);

        return view('app.menu.products.create', [
            'categories' => MenuCategory::query()->orderBy('sort_order')->get(),
            'taxRates' => TaxRate::query()->where('is_active', true)->orderBy('name')->get(),
            'prepStations' => PrepStation::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, CreateProduct $createProduct, FeatureGate $features): RedirectResponse
    {
        $this->authorizeMenu($features);

        try {
            $product = $createProduct->handle($this->productData($request));
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'name_ar' => __('app.limit_reached'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.menu.products.show', $product->public_id)
            ->with('status', __('app.product_saved'));
    }

    public function show(string $publicId, FeatureGate $features): View
    {
        $this->authorizeMenu($features);
        $product = $this->findProduct($publicId);
        $product->load(['category', 'images', 'optionGroups.options', 'branchSettings', 'taxRate', 'recipe.lines.stockItem', 'recipe.lines.unit']);

        return view('app.menu.products.show', [
            'product' => $product,
            'branches' => Branch::query()->orderBy('name_ar')->get(),
            'categories' => MenuCategory::query()->orderBy('sort_order')->get(),
            'taxRates' => TaxRate::query()->where('is_active', true)->orderBy('name')->get(),
            'prepStations' => PrepStation::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'stockItems' => StockItem::query()->where('is_active', true)->with('unit')->orderBy('name_ar')->get(),
            'units' => Unit::query()->orderBy('name_ar')->get(),
            'inventoryEnabled' => $features->allows('module.inventory'),
        ]);
    }

    public function storeRecipe(
        Request $request,
        string $publicId,
        UpsertRecipe $upsertRecipe,
        FeatureGate $features,
    ): RedirectResponse {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('inventory.items.manage'), 403);

        $product = $this->findProduct($publicId);
        $data = $request->validate([
            'yield_qty' => ['nullable', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_id' => ['required', 'integer'],
        ]);

        try {
            $upsertRecipe->handle($product, $data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.recipe_saved'));
    }

    public function update(Request $request, string $publicId, UpdateProduct $updateProduct, FeatureGate $features): RedirectResponse
    {
        $this->authorizeMenu($features);
        $product = $this->findProduct($publicId);

        try {
            $updateProduct->handle($product, $this->productData($request, $product));
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.menu.products.show', $product->public_id)
            ->with('status', __('app.product_saved'));
    }

    public function storeBranchSetting(
        Request $request,
        string $publicId,
        UpsertProductBranchSetting $upsert,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizeMenu($features);
        $product = $this->findProduct($publicId);
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'is_available' => ['nullable', 'boolean'],
            'price_override' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ], [], [
            'branch_id' => __('app.branches'),
            'price_override' => __('app.price_override'),
        ]);

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null) {
            abort(404);
        }

        try {
            $upsert->handle($product, $branch, [
                'is_available' => $request->boolean('is_available', true),
                'price_override' => $data['price_override'] ?? null,
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.branch_setting_saved'));
    }

    public function storeOptionGroup(
        Request $request,
        string $publicId,
        CreateOptionGroup $createOptionGroup,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizeMenu($features);
        $product = $this->findProduct($publicId);
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'kind' => ['required', Rule::in(ProductOptionGroup::KINDS)],
            'selection' => ['required', Rule::in(ProductOptionGroup::SELECTIONS)],
            'is_required' => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], [
            'name_ar' => __('app.name_ar'),
            'name_en' => __('app.name_en'),
            'kind' => __('app.option_kind'),
            'selection' => __('app.option_selection'),
        ]);

        try {
            $createOptionGroup->handle($product, [
                ...$data,
                'is_required' => $request->boolean('is_required'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.option_group_saved'));
    }

    public function storeOption(
        Request $request,
        string $groupPublicId,
        CreateOption $createOption,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizeMenu($features);
        $group = ProductOptionGroup::query()->where('public_id', $groupPublicId)->first() ?? abort(404);
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'price_delta' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], [
            'name_ar' => __('app.name_ar'),
            'name_en' => __('app.name_en'),
            'price_delta' => __('app.price_delta'),
        ]);

        try {
            $createOption->handle($group, [
                ...$data,
                'is_default' => $request->boolean('is_default'),
                'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (ConflictingDefaultOption) {
            throw ValidationException::withMessages([
                'is_default' => __('app.option_default_conflict'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.option_saved'));
    }

    public function storeOptionBranchSetting(
        Request $request,
        string $optionPublicId,
        UpsertOptionBranchSetting $upsert,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizeMenu($features);
        $option = ProductOption::query()->where('public_id', $optionPublicId)->first() ?? abort(404);
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'is_available' => ['nullable', 'boolean'],
            'price_delta_override' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $branch = Branch::query()->find($data['branch_id']) ?? abort(404);

        try {
            $upsert->handle($option, $branch, [
                'is_available' => $request->boolean('is_available', true),
                'price_delta_override' => $data['price_delta_override'] ?? null,
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.branch_setting_saved'));
    }

    public function storeImage(
        Request $request,
        string $publicId,
        StoreProductImage $storeProductImage,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizeMenu($features);
        $product = $this->findProduct($publicId);
        $request->validate([
            'image' => ['required', 'file', 'max:4096'],
            'is_primary' => ['nullable', 'boolean'],
        ], [], [
            'image' => __('app.product_image'),
        ]);

        try {
            $storeProductImage->handle($product, $request->file('image'), $request->boolean('is_primary'));
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'image' => __('app.limit_reached'),
            ]);
        } catch (InvalidProductImage) {
            throw ValidationException::withMessages([
                'image' => __('app.invalid_product_image'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.product_image_saved'));
    }

    /**
     * @return array{
     *     category_id: int,
     *     name_ar: string,
     *     name_en: string,
     *     base_price: mixed,
     *     description_ar: ?string,
     *     description_en: ?string,
     *     sku: ?string,
     *     tax_rate_id: ?int,
     *     prep_station_id: ?int,
     *     is_active: bool
     * }
     */
    private function productData(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'description_ar' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sku' => ['nullable', 'string', 'max:64'],
            'tax_rate_id' => ['nullable', 'integer'],
            'prep_station_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'category_id' => __('app.category'),
            'name_ar' => __('app.name_ar'),
            'name_en' => __('app.name_en'),
            'description_ar' => __('app.description_ar'),
            'description_en' => __('app.description_en'),
            'base_price' => __('app.base_price'),
            'sku' => __('app.sku'),
            'tax_rate_id' => __('app.tax_rate'),
            'prep_station_id' => __('app.prep_station'),
        ]);

        return [
            'category_id' => (int) $data['category_id'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'base_price' => $data['base_price'],
            'sku' => $data['sku'] ?? null,
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'prep_station_id' => $data['prep_station_id'] ?? null,
            'is_active' => $request->boolean('is_active', $product?->is_active ?? true),
        ];
    }

    private function findProduct(string $publicId): Product
    {
        return Product::query()->where('public_id', $publicId)->first() ?? abort(404);
    }

    private function authorizeMenu(FeatureGate $features): void
    {
        abort_unless(auth()->user()->hasPermission('menu.products.manage'), 403);

        if (! $features->allows('module.digital_menu')) {
            abort(404);
        }
    }
}
