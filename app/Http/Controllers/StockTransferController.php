<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\CreateStockTransfer;
use App\Domain\Inventory\Actions\ReceiveStockTransfer;
use App\Domain\Inventory\Actions\SendStockTransfer;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(FeatureGate $features): View
    {
        $this->authorizeTransfers($features);

        return view('app.stock-transfers.index', [
            'transfers' => StockTransfer::query()->with(['fromBranch', 'toBranch'])->latest('id')->limit(40)->get(),
            'branches' => auth()->user()->visibleBranches()->where('is_active', true)->values(),
            'items' => StockItem::query()->where('is_active', true)->orderBy('name_ar')->get(),
            'multiBranch' => $features->allows('module.multi_branch'),
        ]);
    }

    public function store(Request $request, CreateStockTransfer $create, FeatureGate $features): RedirectResponse
    {
        $this->authorizeTransfers($features);

        $data = $request->validate([
            'from_branch_id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $transfer = $create->handle($data);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.stock-transfers.show', $transfer->public_id)
            ->with('status', __('app.stock_transfer_saved'));
    }

    public function show(string $publicId, FeatureGate $features): View
    {
        $this->authorizeTransfers($features);

        $transfer = StockTransfer::query()
            ->where('public_id', $publicId)
            ->with(['lines.stockItem', 'fromBranch', 'toBranch'])
            ->firstOrFail();

        return view('app.stock-transfers.show', ['transfer' => $transfer]);
    }

    public function send(string $publicId, SendStockTransfer $send, FeatureGate $features): RedirectResponse
    {
        $this->authorizeTransfers($features);

        $transfer = StockTransfer::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $send->handle($transfer);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.stock-transfers.show', $transfer->public_id)
            ->with('status', __('app.stock_transfer_sent'));
    }

    public function receive(string $publicId, ReceiveStockTransfer $receive, FeatureGate $features): RedirectResponse
    {
        $this->authorizeTransfers($features);

        $transfer = StockTransfer::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $receive->handle($transfer);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()
            ->route('app.stock-transfers.show', $transfer->public_id)
            ->with('status', __('app.stock_transfer_received'));
    }

    private function authorizeTransfers(FeatureGate $features): void
    {
        abort_unless($features->allows('module.inventory'), 404);
        abort_unless(auth()->user()->hasPermission('inventory.movements.adjust'), 403);
    }
}
