<?php

namespace App\Http\Controllers;

use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\TransitionOrderStatus;
use App\Domain\Ordering\OrderStatusMachine;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderBoardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('orders.orders.view'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branchId = (int) $request->query('branch_id', $branches->first()?->id ?? 0);
        $branch = $branches->firstWhere('id', $branchId) ?? $branches->first();

        abort_if($branch === null, 404);

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', [OrderStatusMachine::COMPLETED, OrderStatusMachine::CANCELLED])
            ->with(['items'])
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get();

        return view('app.orders.index', [
            'branches' => $branches,
            'branch' => $branch,
            'orders' => $orders,
        ]);
    }

    public function poll(Request $request): View
    {
        abort_unless(auth()->user()->hasPermission('orders.orders.view'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branchId = (int) $request->query('branch_id', $branches->first()?->id ?? 0);
        $branch = $branches->firstWhere('id', $branchId) ?? abort(404);

        $orders = Order::query()
            ->where('branch_id', $branch->id)
            ->whereNotIn('status', [OrderStatusMachine::COMPLETED, OrderStatusMachine::CANCELLED])
            ->with(['items'])
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get();

        return view('app.orders._list', [
            'orders' => $orders,
            'branch' => $branch,
        ]);
    }

    public function status(Request $request, string $publicId, TransitionOrderStatus $transition): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('orders.orders.accept'), 403);

        $data = $request->validate([
            'to' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $order = Order::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $transition->handle($order, $data['to']);
        } catch (HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.orders.index', array_filter(['branch_id' => $data['branch_id'] ?? $order->branch_id]))
            ->with('status', __('app.order_status_updated'));
    }

    public function cancel(Request $request, string $publicId, CancelOrder $cancelOrder): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('orders.orders.cancel'), 403);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $order = Order::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $cancelOrder->handle($order);
        } catch (HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.orders.index', array_filter(['branch_id' => $data['branch_id'] ?? $order->branch_id]))
            ->with('status', __('app.order_cancelled'));
    }

}
