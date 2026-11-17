<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateStockCount;
use App\Domain\Inventory\Actions\PostStockCount;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function index(FeatureGate $features): View
    {
        $this->authorizeCounts($features);

        return view('app.stock-counts.index', [
            'counts' => StockCount::query()->with('branch')->latest('id')->limit(40)->get(),
            'branches' => auth()->user()->visibleBranches()->where('is_active', true)->values(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name_ar')->get(),
        ]);
    }

    public function store(Request $request, CreateStockCount $create, FeatureGate $features): RedirectResponse
    {
        $this->authorizeCounts($features);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer'],
            'lines.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $count = $create->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.stock-counts.show', $count->public_id)
            ->with('status', __('app.stock_count_saved'));
    }

    public function show(string $publicId, FeatureGate $features): View
    {
        $this->authorizeCounts($features);

        $count = StockCount::query()
            ->where('public_id', $publicId)
            ->with(['lines.stockItem', 'branch'])
            ->firstOrFail();

        return view('app.stock-counts.show', ['count' => $count]);
    }

    public function post(string $publicId, PostStockCount $post, FeatureGate $features): RedirectResponse
    {
        $this->authorizeCounts($features);

        $count = StockCount::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $post->handle($count);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.stock-counts.show', $count->public_id)
            ->with('status', __('app.stock_count_posted'));
    }

    private function authorizeCounts(FeatureGate $features): void
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('inventory.counts.manage'), 403);
    }
}
