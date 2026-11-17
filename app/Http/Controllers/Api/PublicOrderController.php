<?php

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Ordering\Actions\QuoteGuestOrder;
use App\Domain\Ordering\Support\InvalidCoupon;
use App\Domain\Ordering\Support\ProductUnavailable;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicOrderController extends Controller
{
    public function quote(Request $request, string $slug, QuoteGuestOrder $quoteGuestOrder, FeatureGate $features): JsonResponse
    {
        if (! $features->allows('module.online_ordering')) {
            abort(404);
        }

        try {
            $payload = $this->validatedCart($request);
            $quote = $quoteGuestOrder->handle($payload);

            return response()->json([
                'data' => [
                    'subtotal' => $quote['subtotal'],
                    'discount_total' => $quote['discount_total'],
                    'tax_total' => $quote['tax_total'],
                    'delivery_fee' => $quote['delivery_fee'],
                    'service_charge' => $quote['service_charge'],
                    'total' => $quote['total'],
                    'items' => array_map(static fn (array $item): array => [
                        'name_ar' => $item['name_ar'],
                        'name_en' => $item['name_en'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'options_total' => $item['options_total'],
                        'line_total' => $item['line_total'],
                        'tax_amount' => $item['tax_amount'],
                    ], $quote['items']),
                    'discounts' => $quote['discounts'],
                ],
            ]);
        } catch (InvalidCoupon|ProductUnavailable|ValidationException $exception) {
            throw $exception instanceof ValidationException
                ? $exception
                : ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }
    }

    public function store(Request $request, string $slug, CreateOrder $createOrder, FeatureGate $features): JsonResponse
    {
        if (! $features->allows('module.online_ordering')) {
            abort(404);
        }

        $payload = $this->validatedCart($request, placing: true);

        try {
            $order = $createOrder->handle($payload + [
                'source' => 'online',
            ]);
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'orders' => __('app.orders_monthly_limit'),
            ]);
        } catch (InvalidCoupon|ProductUnavailable $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => $this->orderPayload($order),
        ], 201);
    }

    public function show(Request $request, string $slug, string $orderPublicId): JsonResponse
    {
        $phone = (string) $request->query('phone', '');

        if ($phone === '') {
            abort(404);
        }

        $order = Order::query()
            ->where('public_id', $orderPublicId)
            ->where('customer_phone', $phone)
            ->with(['items.options', 'discounts'])
            ->first();

        if ($order === null) {
            abort(404);
        }

        return response()->json([
            'data' => $this->orderPayload($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCart(Request $request, bool $placing = false): array
    {
        $rules = [
            'branch_id' => ['required', 'string', 'size:26'],
            'type' => ['required', Rule::in(['pickup', 'delivery'])],
            'zone_id' => ['nullable', 'string', 'size:26'],
            'customer_name' => [$placing ? 'required' : 'nullable', 'string', 'max:120'],
            'customer_phone' => [$placing ? 'required' : 'nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => [$placing ? 'required' : 'nullable', 'string', 'size:26'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'numeric', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['string', 'size:26'],
        ];

        if ($request->input('type') === 'delivery') {
            $rules['zone_id'] = ['required', 'string', 'size:26'];
            $rules['address_line'] = [$placing ? 'required' : 'nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $branch = Branch::query()
            ->where('public_id', $data['branch_id'])
            ->where('is_active', true)
            ->first();

        if ($branch === null) {
            throw ValidationException::withMessages(['branch_id' => __('app.branch_unavailable')]);
        }

        $zoneId = null;

        if (($data['type'] ?? '') === 'delivery') {
            $zone = DeliveryZone::query()
                ->where('public_id', $data['zone_id'])
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->first();

            if ($zone === null) {
                throw ValidationException::withMessages(['zone_id' => __('app.delivery_zone_unavailable')]);
            }

            $zoneId = $zone->id;
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
            'branch_id' => $branch->id,
            'type' => $data['type'],
            'delivery_zone_id' => $zoneId,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'delivery_address' => isset($data['address_line'])
                ? ['line' => $data['address_line'], 'area' => null]
                : null,
            'notes' => $data['notes'] ?? null,
            'coupon_code' => $data['coupon_code'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? (string) Str::ulid(),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->public_id,
            'number' => $order->number,
            'type' => $order->type,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'subtotal' => (string) $order->subtotal,
            'discount_total' => (string) $order->discount_total,
            'tax_total' => (string) $order->tax_total,
            'delivery_fee' => (string) $order->delivery_fee,
            'service_charge' => (string) $order->service_charge,
            'total' => (string) $order->total,
            'currency_code' => $order->currency_code,
            'placed_at' => optional($order->placed_at)?->toIso8601String(),
            'items' => $order->items->map(static fn ($item): array => [
                'name_ar' => $item->name_ar,
                'name_en' => $item->name_en,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
            ])->all(),
        ];
    }
}
