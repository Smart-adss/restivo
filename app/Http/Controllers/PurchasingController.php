<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateSupplier;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Actions\UpsertPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchasingController extends Controller
{
    public function index(FeatureGate $features): View
    {
        $this->authorizePurchasing($features);

        return view('app.purchasing.index', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'orders' => PurchaseOrder::query()->with(['supplier', 'branch'])->latest('id')->limit(40)->get(),
            'branches' => auth()->user()->visibleBranches()->where('is_active', true)->values(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name_ar')->get(),
        ]);
    }

    public function storeSupplier(Request $request, CreateSupplier $createSupplier, FeatureGate $features): RedirectResponse
    {
        $this->authorizePurchasing($features);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $createSupplier->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.supplier_created'));
    }

    public function storeOrder(Request $request, UpsertPurchaseOrder $upsert, FeatureGate $features): RedirectResponse
    {
        $this->authorizePurchasing($features);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'expected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $order = $upsert->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.purchasing.show', $order->public_id)
            ->with('status', __('app.purchase_order_saved'));
    }

    public function show(string $publicId, FeatureGate $features): View
    {
        $this->authorizePurchasing($features);

        $order = PurchaseOrder::query()
            ->where('public_id', $publicId)
            ->with(['lines.stockItem', 'supplier', 'branch', 'receipts'])
            ->firstOrFail();

        return view('app.purchasing.show', [
            'order' => $order,
        ]);
    }

    public function updateOrder(
        Request $request,
        string $publicId,
        UpsertPurchaseOrder $upsert,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizePurchasing($features);

        $order = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'expected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $upsert->handle($data, $order);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.purchasing.show', $order->public_id)
            ->with('status', __('app.purchase_order_saved'));
    }

    public function receive(
        Request $request,
        string $publicId,
        ReceivePurchaseOrder $receive,
        FeatureGate $features,
    ): RedirectResponse {
        $this->authorizePurchasing($features);

        $order = PurchaseOrder::query()->where('public_id', $publicId)->firstOrFail();

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $receive->handle($order, $data['lines']);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.purchasing.show', $order->public_id)
            ->with('status', __('app.purchase_received'));
    }

    private function authorizePurchasing(FeatureGate $features): void
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('purchasing.orders.manage'), 403);
    }
}
