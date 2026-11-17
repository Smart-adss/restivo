<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\PricingEngine;
use App\Domain\Ordering\Support\ProductUnavailable;
use App\Models\Branch;
use App\Models\BranchOrderSequence;
use App\Models\CouponRedemption;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Features\Usage;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder
{
    public function __construct(
        private PricingEngine $pricing,
        private ResolveCoupon $resolveCoupon,
        private UpsertCustomerByPhone $upsertCustomer,
        private FeatureGate $features,
        private Usage $usage,
    ) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   type: string,
     *   source: string,
     *   idempotency_key: string,
     *   customer_name?: ?string,
     *   customer_phone?: ?string,
     *   delivery_zone_id?: ?int,
     *   delivery_address?: ?array,
     *   notes?: ?string,
     *   coupon_code?: ?string,
     *   cashier_shift_id?: ?int,
     *   table_session_id?: ?int,
     *   status?: string,
     *   items: list<array{
     *     product_id: int,
     *     quantity: numeric-string|float|int,
     *     notes?: ?string,
     *     option_ids?: list<int>
     *   }>
     * }  $data
     */
    public function handle(array $data): Order
    {
        $existing = Order::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing !== null) {
            return $existing->load(['items.options', 'discounts']);
        }

        try {
            return DB::transaction(function () use ($data): Order {
                $existing = Order::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['items.options', 'discounts']);
                }

                $this->assertSourceModule($data['source']);
                $this->usage->assertCanConsume('orders.monthly');

                $branch = Branch::query()->find($data['branch_id']);

                if ($branch === null || ! $branch->is_active) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'The selected branch is unavailable.',
                    ]);
                }

                $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
                $settings = $this->tenantSettings();
                $pricesIncludeTax = filter_var($settings['tax.prices_include_tax'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $serviceChargePercent = (string) ($settings['order.service_charge_percent'] ?? '0');
                $serviceChargeTaxable = filter_var($settings['order.service_charge_taxable'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $pricedLines = $this->resolveLines($data['items'], $branch);
                $grossSubtotal = '0.00';
                foreach ($pricedLines as $line) {
                    $unit = Money::add($line['unit_price'], $line['options_per_unit']);
                    $grossSubtotal = Money::add($grossSubtotal, Money::mul($unit, $line['quantity']));
                }

                $deliveryFee = '0.00';
                $deliveryZoneId = $data['delivery_zone_id'] ?? null;

                if (($data['type'] ?? '') === 'delivery') {
                    if ($deliveryZoneId === null) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'A delivery zone is required.',
                        ]);
                    }

                    $zone = DeliveryZone::query()
                        ->whereKey($deliveryZoneId)
                        ->where('branch_id', $branch->id)
                        ->where('is_active', true)
                        ->first();

                    if ($zone === null) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'The delivery zone is unavailable.',
                        ]);
                    }

                    if ($zone->min_order !== null && Money::compare($grossSubtotal, (string) $zone->min_order) === -1) {
                        throw ValidationException::withMessages([
                            'delivery_zone_id' => 'The order is below the delivery zone minimum.',
                        ]);
                    }

                    $deliveryFee = Money::of((string) $zone->fee);
                }

                $couponDiscount = null;
                $couponCode = $data['coupon_code'] ?? null;

                if ($couponCode !== null && $couponCode !== '') {
                    $couponDiscount = $this->resolveCoupon->handle(
                        $couponCode,
                        $branch,
                        $grossSubtotal,
                        $data['customer_phone'] ?? null,
                    );
                }

                $quoteLines = array_map(static function (array $line): array {
                    return [
                        'product_id' => $line['product_id'],
                        'name_ar' => $line['name_ar'],
                        'name_en' => $line['name_en'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'tax_rate_percent' => $line['tax_rate_percent'],
                        'notes' => $line['notes'],
                        'options' => $line['options'],
                    ];
                }, $pricedLines);

                $quote = $this->pricing->quote(
                    $quoteLines,
                    $pricesIncludeTax,
                    '0',
                    $deliveryFee,
                    $serviceChargePercent,
                    $serviceChargeTaxable,
                    $couponDiscount === null ? null : [
                        'kind' => $couponDiscount['kind'],
                        'coupon_id' => $couponDiscount['coupon_id'],
                        'label' => $couponDiscount['label'],
                        'amount' => $couponDiscount['amount'],
                    ],
                );

                $number = $this->nextNumber($branch->id);
                $placedAt = now();

                $customerId = null;
                $customerPhone = $data['customer_phone'] ?? null;
                $customerName = $data['customer_name'] ?? null;

                if ($customerPhone !== null && $customerPhone !== '') {
                    $addressLine = is_array($data['delivery_address'] ?? null)
                        ? ($data['delivery_address']['line'] ?? $data['delivery_address']['address_line'] ?? null)
                        : null;
                    $areaName = is_array($data['delivery_address'] ?? null)
                        ? ($data['delivery_address']['area'] ?? $data['delivery_address']['area_name'] ?? null)
                        : null;

                    $customer = $this->upsertCustomer->handle(
                        $customerPhone,
                        (string) ($customerName ?? ''),
                        $addressLine,
                        $areaName,
                    );
                    $customerId = $customer->id;
                    $customerName = $customer->name;
                }

                $status = $data['status'] ?? 'placed';
                $tableSessionId = $this->resolveTableSessionId(
                    $data['table_session_id'] ?? null,
                    $branch->id,
                    $data['type'],
                );

                $order = Order::query()->create([
                    'branch_id' => $branch->id,
                    'number' => $number,
                    'type' => $data['type'],
                    'source' => $data['source'],
                    'status' => $status,
                    'cashier_shift_id' => $data['cashier_shift_id'] ?? null,
                    'table_session_id' => $tableSessionId,
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'delivery_zone_id' => $deliveryZoneId,
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'subtotal' => $quote['subtotal'],
                    'discount_total' => $quote['discount_total'],
                    'tax_total' => $quote['tax_total'],
                    'delivery_fee' => $quote['delivery_fee'],
                    'service_charge' => $quote['service_charge'],
                    'total' => $quote['total'],
                    'currency_code' => $tenant->currency_code,
                    'prices_include_tax' => $pricesIncludeTax,
                    'idempotency_key' => $data['idempotency_key'],
                    'placed_at' => $placedAt,
                    'completed_at' => $status === 'completed' ? $placedAt : null,
                ]);

                foreach ($quote['items'] as $item) {
                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'name_ar' => $item['name_ar'],
                        'name_en' => $item['name_en'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'options_total' => $item['options_total'],
                        'discount_amount' => $item['discount_amount'],
                        'tax_amount' => $item['tax_amount'],
                        'tax_rate_percent' => $item['tax_rate_percent'],
                        'line_total' => $item['line_total'],
                        'notes' => $item['notes'],
                        'status' => 'pending',
                    ]);

                    foreach ($item['options'] as $option) {
                        OrderItemOption::query()->create([
                            'order_item_id' => $orderItem->id,
                            'name_ar' => $option['name_ar'],
                            'name_en' => $option['name_en'],
                            'price_delta' => $option['price_delta'],
                            'quantity' => $option['quantity'],
                        ]);
                    }
                }

                foreach ($quote['discounts'] as $discount) {
                    OrderDiscount::query()->create([
                        'order_id' => $order->id,
                        'kind' => $discount['kind'],
                        'coupon_id' => $discount['coupon_id'],
                        'label' => $discount['label'],
                        'amount' => $discount['amount'],
                    ]);
                }

                if ($couponDiscount !== null) {
                    CouponRedemption::query()->create([
                        'coupon_id' => $couponDiscount['coupon_id'],
                        'order_id' => $order->id,
                        'phone' => $data['customer_phone'] ?? '',
                        'created_at' => $placedAt,
                    ]);
                }

                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => $status,
                    'user_id' => auth()->id(),
                    'created_at' => $placedAt,
                ]);

                if ($tableSessionId !== null) {
                    DB::table('table_session_orders')->insert([
                        'tenant_id' => $order->tenant_id,
                        'table_session_id' => $tableSessionId,
                        'order_id' => $order->id,
                    ]);
                }

                return $order->load(['items.options', 'discounts']);
            });
        } catch (QueryException $exception) {
            if ($this->isIdempotencyConflict($exception)) {
                $order = Order::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($order !== null) {
                    return $order->load(['items.options', 'discounts']);
                }
            }

            throw $exception;
        }
    }

    private function assertSourceModule(string $source): void
    {
        $this->features->assertWritable();

        $module = match ($source) {
            'pos' => 'module.pos',
            'online', 'qr_table' => 'module.online_ordering',
            default => throw ValidationException::withMessages([
                'source' => 'Unsupported order source.',
            ]),
        };

        if (! $this->features->allows($module)) {
            abort(404);
        }
    }

    private function resolveTableSessionId(mixed $sessionId, int $branchId, string $type): ?int
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        $session = TableSession::query()
            ->whereKey((int) $sessionId)
            ->where('status', 'open')
            ->with('table')
            ->first();

        if ($session === null || $session->table === null || (int) $session->table->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'table_session_id' => __('app.table_session_unavailable'),
            ]);
        }

        if ($type !== 'dine_in') {
            throw ValidationException::withMessages([
                'type' => __('app.table_session_requires_dine_in'),
            ]);
        }

        return $session->id;
    }

    /**
     * @param  list<array{product_id: int, quantity: mixed, notes?: ?string, option_ids?: list<int>}>  $items
     * @return list<array<string, mixed>>
     */
    private function resolveLines(array $items, Branch $branch): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one item is required.',
            ]);
        }

        $lines = [];

        foreach ($items as $index => $item) {
            $product = Product::query()
                ->with(['taxRate', 'optionGroups.options.branchSettings', 'branchSettings'])
                ->find($item['product_id'] ?? null);

            if ($product === null || ! $product->isAvailableAt($branch)) {
                throw new ProductUnavailable("Item {$index} is unavailable at this branch.");
            }

            $optionIds = $item['option_ids'] ?? [];
            $selected = [];
            $optionsPayload = [];
            $optionsPerUnit = '0.00';

            if ($optionIds !== []) {
                $options = ProductOption::query()
                    ->with(['group', 'branchSettings'])
                    ->whereIn('id', $optionIds)
                    ->get();

                if ($options->count() !== count(array_unique($optionIds))) {
                    throw new ProductUnavailable("Item {$index} has an unknown option.");
                }

                foreach ($options as $option) {
                    if ((int) $option->group->product_id !== (int) $product->id) {
                        throw new ProductUnavailable("Item {$index} has an option from another product.");
                    }

                    if (! $option->isAvailableAt($branch)) {
                        throw new ProductUnavailable("Item {$index} has an unavailable option.");
                    }

                    $groupId = $option->group_id;
                    $selected[$groupId] = ($selected[$groupId] ?? 0) + 1;
                    $delta = Money::of($option->priceDeltaAt($branch));
                    $optionsPerUnit = Money::add($optionsPerUnit, $delta);
                    $optionsPayload[] = [
                        'name_ar' => $option->name_ar,
                        'name_en' => $option->name_en,
                        'price_delta' => $delta,
                        'quantity' => '1.000',
                    ];
                }
            }

            foreach ($product->optionGroups as $group) {
                $count = $selected[$group->id] ?? 0;

                if ($group->is_required && $count < max(1, (int) $group->min_select)) {
                    throw new ProductUnavailable("Item {$index} is missing a required option group.");
                }

                if ($group->max_select !== null && $count > (int) $group->max_select) {
                    throw new ProductUnavailable("Item {$index} exceeds an option group limit.");
                }

                if ($group->isSingle() && $count > 1) {
                    throw new ProductUnavailable("Item {$index} selected too many options in a single group.");
                }
            }

            $taxRate = $product->taxRate;

            $lines[] = [
                'product_id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'quantity' => Money::of($item['quantity']),
                'unit_price' => Money::of($product->priceAt($branch)),
                'options_per_unit' => $optionsPerUnit,
                'tax_rate_percent' => $taxRate ? Money::of((string) $taxRate->rate_percent) : '0.00',
                'notes' => $item['notes'] ?? null,
                'options' => $optionsPayload,
            ];
        }

        return $lines;
    }

    private function nextNumber(int $branchId): int
    {
        $sequence = BranchOrderSequence::query()
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            try {
                BranchOrderSequence::query()->create([
                    'branch_id' => $branchId,
                    'last_number' => 0,
                ]);
            } catch (QueryException) {
                // Concurrent create — continue with lock.
            }

            $sequence = BranchOrderSequence::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->last_number++;
        $sequence->save();

        return $sequence->last_number;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSettings(): array
    {
        $rows = DB::table('tenant_settings')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->pluck('value', 'key');

        $settings = [];

        foreach ($rows as $key => $value) {
            $settings[$key] = json_decode((string) $value, true);
        }

        return $settings;
    }

    private function isIdempotencyConflict(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'idempotency_key')
            || str_contains($message, 'orders_tenant_id_idempotency_key_unique')
            || str_contains($message, 'UNIQUE constraint failed: orders.tenant_id, orders.idempotency_key');
    }
}
