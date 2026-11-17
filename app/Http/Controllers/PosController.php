<?php

namespace App\Http\Controllers;

use App\Domain\Ordering\Actions\ApplyManualDiscount;
use App\Domain\Ordering\Support\ProductUnavailable;
use App\Domain\Pos\Actions\CloseShift;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\CreateRefund;
use App\Domain\Pos\Actions\EnsureReceiptTemplate;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Pos\Actions\RecordCashMovement;
use App\Support\Features\LimitExceeded;
use App\Models\CashierShift;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Support\Features\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PosController extends Controller
{
    public function show(Request $request, FeatureGate $gate, EnsureReceiptTemplate $ensureReceipt): View
    {
        abort_unless($gate->allows('module.pos'), 403);
        abort_unless(auth()->user()->hasPermission('pos.sale.create') || auth()->user()->hasPermission('pos.shift.open'), 403);

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $registers = CashRegister::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($registers->isEmpty()) {
            $registers = collect([
                CashRegister::query()->create([
                    'branch_id' => $branch->id,
                    'name' => __('app.main_register'),
                    'is_active' => true,
                ]),
            ]);
        }

        $openShift = CashierShift::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->with('register')
            ->first();

        $products = Product::query()
            ->with(['branchSettings', 'optionGroups' => fn ($q) => $q->orderBy('sort_order')->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')->with('branchSettings')])])
            ->where('is_active', true)
            ->orderBy('name_ar')
            ->get()
            ->filter(fn (Product $product) => $product->isAvailableAt($branch))
            ->map(function (Product $product) use ($branch): array {
                return [
                    'id' => $product->id,
                    'public_id' => $product->public_id,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'price' => $product->priceAt($branch),
                    'option_groups' => $product->optionGroups->map(function ($group) use ($branch): array {
                        return [
                            'public_id' => $group->public_id,
                            'name_ar' => $group->name_ar,
                            'name_en' => $group->name_en,
                            'selection' => $group->selection,
                            'is_required' => (bool) $group->is_required,
                            'min_select' => (int) $group->min_select,
                            'max_select' => (int) $group->max_select,
                            'options' => $group->options
                                ->filter(fn ($option) => $option->isAvailableAt($branch))
                                ->map(fn ($option): array => [
                                    'id' => $option->id,
                                    'public_id' => $option->public_id,
                                    'name_ar' => $option->name_ar,
                                    'name_en' => $option->name_en,
                                    'price_delta' => $option->priceDeltaAt($branch),
                                ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $ensureReceipt->handle();

        return view('app.pos', [
            'branches' => $branches,
            'branch' => $branch,
            'registers' => $registers,
            'openShift' => $openShift,
            'products' => $products,
            'locale' => app()->getLocale(),
        ]);
    }

    public function store(FeatureGate $gate): RedirectResponse
    {
        // Kept for Sprint 3 feature-gate smoke tests.
        abort_unless($gate->allows('module.pos'), 403);
        $gate->assertWritable();

        return redirect()->route('app.pos')->with('status', __('app.pos_recorded'));
    }

    public function openShift(Request $request, OpenShift $openShift): RedirectResponse
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer'],
            'opening_float' => ['required', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        try {
            $openShift->handle((int) $data['register_id'], (string) $data['opening_float']);
        } catch (HttpException $exception) {
            return back()->withErrors(['shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.pos', array_filter(['branch_id' => $data['branch_id'] ?? null]))
            ->with('status', __('app.shift_opened'));
    }

    public function closeShift(Request $request, string $publicId, CloseShift $closeShift): RedirectResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $shift = CashierShift::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $closeShift->handle($shift, (string) $data['counted_cash']);
        } catch (HttpException $exception) {
            return back()->withErrors(['shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.pos', array_filter(['branch_id' => $data['branch_id'] ?? $shift->branch_id]))
            ->with('status', __('app.shift_closed'));
    }

    public function movement(Request $request, string $publicId, RecordCashMovement $record): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:pay_in,pay_out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $shift = CashierShift::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $record->handle($shift, $data['kind'], (string) $data['amount'], $data['note'] ?? null);
        } catch (HttpException $exception) {
            return back()->withErrors(['shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.pos', array_filter(['branch_id' => $data['branch_id'] ?? $shift->branch_id]))
            ->with('status', __('app.cash_movement_recorded'));
    }

    public function sale(Request $request, CreatePosSale $createPosSale): JsonResponse
    {
        $data = $request->validate([
            'shift_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'string', 'size:26'],
            'type' => ['nullable', 'in:dine_in,pickup'],
            'notes' => ['nullable', 'string', 'max:500'],
            'table_session_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,wallet,instapay'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $result = $createPosSale->handle($data);
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            if (isset($messages['expected_total'][0])) {
                return response()->json([
                    'message' => $messages['total'][0] ?? 'Payment mismatch',
                    'error' => [
                        'fields' => [
                            'total' => $messages['expected_total'][0],
                        ],
                    ],
                ], 422);
            }

            throw $exception;
        } catch (ProductUnavailable|LimitExceeded $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $order = $result['order'];

        return response()->json([
            'data' => [
                'id' => $order->public_id,
                'number' => $order->number,
                'total' => (string) $order->total,
                'status' => $order->status,
                'receipt_url' => route('app.pos.receipt', $order->public_id),
                'payments' => collect($result['payments'])->map(fn (Payment $payment): array => [
                    'method' => $payment->method,
                    'amount' => (string) $payment->amount,
                ])->all(),
            ],
        ], 201);
    }

    public function receipt(string $publicId, EnsureReceiptTemplate $ensureReceipt): View
    {
        abort_unless(auth()->user()->hasPermission('pos.sale.create') || auth()->user()->hasPermission('orders.orders.view'), 403);

        $order = Order::query()
            ->where('public_id', $publicId)
            ->with(['items.options', 'payments', 'branch'])
            ->firstOrFail();

        $template = $ensureReceipt->handle();

        return view('app.pos.receipt', [
            'order' => $order,
            'template' => $template,
        ]);
    }

    public function refund(Request $request, string $publicId, CreateRefund $createRefund): JsonResponse
    {
        $order = Order::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($order->branch), 404);

        $data = $request->validate([
            'payment_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'size:26'],
        ]);

        $payment = Payment::query()
            ->where('public_id', $data['payment_id'])
            ->where('order_id', $order->id)
            ->first();

        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment_id' => __('app.refund_payment_unavailable'),
            ]);
        }

        try {
            $refund = $createRefund->handle($order, [
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'idempotency_key' => $data['idempotency_key'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'error' => ['fields' => $exception->errors()],
            ], 422);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        return response()->json([
            'data' => [
                'id' => $refund->public_id,
                'amount' => (string) $refund->amount,
                'status' => $refund->status,
            ],
        ], 201);
    }

    public function manualDiscount(Request $request, string $publicId, ApplyManualDiscount $applyManualDiscount): JsonResponse
    {
        $order = Order::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($order->branch), 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:190'],
        ]);

        try {
            $discount = $applyManualDiscount->handle($order, (string) $data['amount'], $data['reason']);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'error' => ['fields' => $exception->errors()],
            ], 422);
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $order->refresh();

        return response()->json([
            'data' => [
                'label' => $discount->label,
                'amount' => (string) $discount->amount,
                'order_total' => (string) $order->total,
                'discount_total' => (string) $order->discount_total,
            ],
        ], 201);
    }
}
