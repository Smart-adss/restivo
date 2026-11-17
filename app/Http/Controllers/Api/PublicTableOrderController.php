<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Ordering\Actions\QuoteGuestOrder;
use App\Domain\Ordering\Support\InvalidCoupon;
use App\Domain\Ordering\Support\ProductUnavailable;
use App\Domain\Tables\Actions\EnsureOpenSessionForTable;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductOption;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicTableOrderController extends Controller
{
    public function quote(
        Request $request,
        string $slug,
        string $tablePublicId,
        QuoteGuestOrder $quoteGuestOrder,
        FeatureGate $features,
    ): JsonResponse {
        if (! $features->allows('module.online_ordering')) {
            abort(404);
        }

        $table = $this->resolveTable($tablePublicId, $request);

        try {
            $payload = $this->validatedCart($request, $table, placing: false);
            $quote = $quoteGuestOrder->handle($payload);

            return response()->json([
                'data' => [
                    'subtotal' => $quote['subtotal'],
                    'discount_total' => $quote['discount_total'],
                    'tax_total' => $quote['tax_total'],
                    'delivery_fee' => $quote['delivery_fee'],
                    'service_charge' => $quote['service_charge'],
                    'total' => $quote['total'],
                ],
            ]);
        } catch (InvalidCoupon|ProductUnavailable|ValidationException $exception) {
            throw $exception instanceof ValidationException
                ? $exception
                : ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }
    }

    public function store(
        Request $request,
        string $slug,
        string $tablePublicId,
        CreateOrder $createOrder,
        EnsureOpenSessionForTable $ensureOpenSession,
        FeatureGate $features,
    ): JsonResponse {
        if (! $features->allows('module.online_ordering')) {
            abort(404);
        }

        $table = $this->resolveTable($tablePublicId, $request);
        $payload = $this->validatedCart($request, $table, placing: true);
        $session = $ensureOpenSession->handle($table, (int) ($request->input('guest_count') ?? 1));

        try {
            $order = $createOrder->handle($payload + [
                'source' => 'qr_table',
                'table_session_id' => $session->id,
            ]);
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'orders' => __('app.orders_monthly_limit'),
            ]);
        } catch (InvalidCoupon|ProductUnavailable $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => [
                'id' => $order->public_id,
                'number' => $order->number,
                'status' => $order->status,
                'total' => (string) $order->total,
                'table_session_id' => $session->public_id,
            ],
        ], 201);
    }

    private function resolveTable(string $tablePublicId, Request $request): DiningTable
    {
        $table = DiningTable::query()->where('public_id', $tablePublicId)->first();

        if ($table === null) {
            abort(404);
        }

        $branchPublicId = (string) $request->input('branch_id', $request->query('branch', ''));

        if ($branchPublicId !== '' && $branchPublicId !== $table->branch->public_id) {
            abort(404);
        }

        return $table;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCart(Request $request, DiningTable $table, bool $placing): array
    {
        $rules = [
            'branch_id' => ['nullable', 'string', 'size:26'],
            'customer_name' => [$placing ? 'required' : 'nullable', 'string', 'max:120'],
            'customer_phone' => [$placing ? 'required' : 'nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => [$placing ? 'required' : 'nullable', 'string', 'size:26'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'numeric', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['string', 'size:26'],
        ];

        $data = $request->validate($rules);

        if (! empty($data['branch_id']) && $data['branch_id'] !== $table->branch->public_id) {
            abort(404);
        }

        $items = [];

        foreach ($data['items'] as $index => $item) {
            $product = Product::query()->where('public_id', $item['product_id'])->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => __('app.product_unavailable'),
                ]);
            }

            $optionIds = [];

            foreach ($item['option_ids'] ?? [] as $optionPublicId) {
                $option = ProductOption::query()->where('public_id', $optionPublicId)->first();

                if ($option === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.option_ids" => __('app.option_unavailable'),
                    ]);
                }

                $optionIds[] = $option->id;
            }

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'notes' => $item['notes'] ?? null,
                'option_ids' => $optionIds,
            ];
        }

        return [
            'branch_id' => $table->branch_id,
            'type' => 'dine_in',
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? (string) Str::ulid(),
            'items' => $items,
        ];
    }
}
