<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateStockItem;
use App\Domain\Inventory\Actions\CreateUnit;
use App\Domain\Inventory\Actions\CreateUnitConversion;
use App\Domain\Inventory\Actions\RecalculateBranchStocks;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Models\BranchStock;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Support\Features\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request, FeatureGate $features): View
    {
        $this->authorizeInventory($features);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $stocks = BranchStock::query()
            ->where('branch_id', $branch->id)
            ->with(['stockItem.unit'])
            ->get()
            ->sortBy(fn (BranchStock $stock) => $stock->stockItem?->name_ar);

        $movements = StockMovement::query()
            ->where('branch_id', $branch->id)
            ->with('stockItem')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('app.inventory.index', [
            'branches' => $branches,
            'branch' => $branch,
            'units' => Unit::query()->orderBy('name_ar')->get(),
            'conversions' => UnitConversion::query()->with(['fromUnit', 'toUnit'])->get(),
            'items' => StockItem::query()->with('unit')->orderBy('name_ar')->get(),
            'stocks' => $stocks,
            'movements' => $movements,
        ]);
    }

    public function storeUnit(Request $request, CreateUnit $createUnit, FeatureGate $features): RedirectResponse
    {
        $this->authorizeInventory($features);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:80'],
            'abbreviation' => ['required', 'string', 'max:20'],
            'kind' => ['required', 'in:weight,volume,count'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $createUnit->handle($data);

        return redirect()
            ->route('app.inventory.index', array_filter(['branch_id' => $data['branch_id'] ?? null]))
            ->with('status', __('app.unit_created'));
    }

    public function storeConversion(Request $request, CreateUnitConversion $createUnitConversion, FeatureGate $features): RedirectResponse
    {
        $this->authorizeInventory($features);

        $data = $request->validate([
            'from_unit_id' => ['required', 'integer'],
            'to_unit_id' => ['required', 'integer'],
            'factor' => ['required', 'numeric', 'gt:0'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $createUnitConversion->handle($data);

        return redirect()
            ->route('app.inventory.index', array_filter(['branch_id' => $data['branch_id'] ?? null]))
            ->with('status', __('app.unit_conversion_created'));
    }

    public function storeItem(Request $request, CreateStockItem $createStockItem, FeatureGate $features): RedirectResponse
    {
        $this->authorizeInventory($features);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['required', 'string', 'max:190'],
            'unit_id' => ['required', 'integer'],
            'kind' => ['required', 'in:ingredient,packaging,resale'],
            'sku' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $createStockItem->handle($data);

        return redirect()
            ->route('app.inventory.index', array_filter(['branch_id' => $data['branch_id'] ?? null]))
            ->with('status', __('app.stock_item_created'));
    }

    public function storeMovement(Request $request, RecordStockMovement $recordStockMovement, FeatureGate $features): RedirectResponse
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('inventory.movements.adjust'), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'stock_item_id' => ['required', 'integer'],
            'kind' => ['required', 'in:adjustment,waste'],
            'quantity' => ['required', 'numeric'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $recordStockMovement->handle($data);

        return redirect()
            ->route('app.inventory.index', ['branch_id' => $data['branch_id']])
            ->with('status', __('app.stock_movement_recorded'));
    }

    public function recalculate(Request $request, RecalculateBranchStocks $recalculate, FeatureGate $features): RedirectResponse
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('inventory.movements.adjust'), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $result = $recalculate->handle((int) $data['branch_id']);

        return redirect()
            ->route('app.inventory.index', ['branch_id' => $data['branch_id']])
            ->with('status', __('app.stock_recalculated', ['count' => $result['updated']]));
    }

    private function authorizeInventory(FeatureGate $features): void
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(
            auth()->user()->hasPermission('inventory.items.manage')
            || auth()->user()->hasPermission('inventory.movements.adjust'),
            403,
        );
    }
}
