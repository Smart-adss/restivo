<?php

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\PricingEngine;
use App\Domain\Ordering\Support\ProductUnavailable;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductOption;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteGuestOrder
{
    public function __construct(
        private PricingEngine $pricing,
        private ResolveCoupon $resolveCoupon,
        private FeatureGate $features,
    ) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   type: string,
     *   delivery_zone_id?: ?int,
     *   coupon_code?: ?string,
     *   customer_phone?: ?string,
     *   items: list<array{product_id: int, quantity: mixed, notes?: ?string, option_ids?: list<int>}>
     * }  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.online_ordering')) {
            abort(404);
        }

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null || ! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => 'The selected branch is unavailable.',
            ]);
        }

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

        return $this->pricing->quote(
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
}
