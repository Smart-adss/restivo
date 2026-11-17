<?php

namespace App\Domain\Ordering;

use App\Support\Money\Money;

final class PricingEngine
{
    /**
     * @param  list<array{
     *   name_ar: string,
     *   name_en: string,
     *   product_id?: int|null,
     *   quantity: string|float|int,
     *   unit_price: string|float|int,
     *   tax_rate_percent?: string|float|int,
     *   notes?: string|null,
     *   options?: list<array{
     *     name_ar: string,
     *     name_en: string,
     *     price_delta?: string|float|int,
     *     quantity?: string|float|int
     *   }>
     * }>  $lines
     * @return array{
     *   items: list<array<string, mixed>>,
     *   subtotal: string,
     *   discount_total: string,
     *   tax_total: string,
     *   delivery_fee: string,
     *   service_charge: string,
     *   total: string,
     *   discounts: list<array{kind: string, coupon_id: int|null, label: string, amount: string}>
     * }
     */
    public function quote(
        array $lines,
        bool $pricesIncludeTax,
        string $invoiceDiscountAmount = '0',
        string $deliveryFee = '0',
        string $serviceChargePercent = '0',
        bool $serviceChargeTaxable = false,
        ?array $couponDiscount = null,
    ): array {
        $draftItems = [];

        foreach ($lines as $line) {
            $quantity = Money::of($line['quantity']);
            $unitPrice = Money::of($line['unit_price']);
            $options = [];
            $optionsPerUnit = '0.00';

            foreach ($line['options'] ?? [] as $option) {
                $optionQty = Money::of($option['quantity'] ?? 1);
                $delta = Money::of($option['price_delta'] ?? 0);
                $optionsPerUnit = Money::add($optionsPerUnit, Money::mul($delta, $optionQty));
                $options[] = [
                    'name_ar' => $option['name_ar'],
                    'name_en' => $option['name_en'],
                    'price_delta' => $delta,
                    'quantity' => $optionQty,
                ];
            }

            $unitWithOptions = Money::add($unitPrice, $optionsPerUnit);
            $grossLine = Money::mul($unitWithOptions, $quantity);
            $taxRate = Money::of($line['tax_rate_percent'] ?? 0);

            $draftItems[] = [
                'product_id' => $line['product_id'] ?? null,
                'name_ar' => $line['name_ar'],
                'name_en' => $line['name_en'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'options_total' => $optionsPerUnit,
                'gross_before_discount' => $grossLine,
                'line_discount' => '0.00',
                'tax_rate_percent' => $taxRate,
                'notes' => $line['notes'] ?? null,
                'options' => $options,
            ];
        }

        $grossSubtotal = '0.00';
        foreach ($draftItems as $item) {
            $grossSubtotal = Money::add($grossSubtotal, $item['gross_before_discount']);
        }

        $discounts = [];
        $invoiceDiscount = Money::of($invoiceDiscountAmount);

        if ($couponDiscount !== null) {
            $amount = Money::of($couponDiscount['amount']);
            $invoiceDiscount = Money::add($invoiceDiscount, $amount);
            $discounts[] = [
                'kind' => $couponDiscount['kind'],
                'coupon_id' => $couponDiscount['coupon_id'] ?? null,
                'label' => $couponDiscount['label'],
                'amount' => $amount,
            ];
        }

        if (Money::compare($invoiceDiscount, $grossSubtotal) === 1) {
            $invoiceDiscount = $grossSubtotal;
            if ($discounts !== []) {
                $discounts[0]['amount'] = $invoiceDiscount;
            }
        }

        $allocated = $this->allocateProportionally(
            array_column($draftItems, 'gross_before_discount'),
            $invoiceDiscount,
        );

        foreach ($draftItems as $index => &$item) {
            $item['line_discount'] = $allocated[$index] ?? '0.00';
            $taxableGross = Money::sub($item['gross_before_discount'], $item['line_discount']);

            if ($pricesIncludeTax) {
                $parts = Money::extractInclusiveTax($taxableGross, $item['tax_rate_percent']);
                $item['tax_amount'] = $parts['tax'];
                $item['line_total'] = $taxableGross;
                $item['net_amount'] = $parts['net'];
            } else {
                $item['tax_amount'] = Money::percent($taxableGross, $item['tax_rate_percent']);
                $item['net_amount'] = $taxableGross;
                $item['line_total'] = Money::add($taxableGross, $item['tax_amount']);
            }

            $item['discount_amount'] = $item['line_discount'];
        }
        unset($item);

        $subtotal = '0.00';
        $taxTotal = '0.00';
        $discountTotal = '0.00';

        foreach ($draftItems as $item) {
            $subtotal = Money::add($subtotal, $item['net_amount']);
            $taxTotal = Money::add($taxTotal, $item['tax_amount']);
            $discountTotal = Money::add($discountTotal, $item['discount_amount']);
        }

        $deliveryFee = Money::of($deliveryFee);
        $serviceChargeBase = $subtotal;
        $serviceCharge = Money::percent($serviceChargeBase, $serviceChargePercent);
        $serviceTax = '0.00';

        if ($serviceChargeTaxable && Money::compare($serviceCharge, '0') === 1) {
            // Service charge tax uses the dominant first-line rate when taxable.
            $rate = $draftItems[0]['tax_rate_percent'] ?? '0.00';
            if ($pricesIncludeTax) {
                $parts = Money::extractInclusiveTax($serviceCharge, $rate);
                $serviceTax = $parts['tax'];
                $serviceCharge = $parts['net'];
            } else {
                $serviceTax = Money::percent($serviceCharge, $rate);
            }
            $taxTotal = Money::add($taxTotal, $serviceTax);
        }

        $total = Money::add($subtotal, $taxTotal, $deliveryFee, $serviceCharge);

        $items = array_map(static function (array $item): array {
            return [
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
                'options' => $item['options'],
            ];
        }, $draftItems);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'delivery_fee' => $deliveryFee,
            'service_charge' => $serviceCharge,
            'total' => $total,
            'discounts' => $discounts,
        ];
    }

    /**
     * @param  list<string>  $weights
     * @return list<string>
     */
    private function allocateProportionally(array $weights, string $amount): array
    {
        $count = count($weights);

        if ($count === 0 || Money::compare($amount, '0') !== 1) {
            return array_fill(0, $count, '0.00');
        }

        $totalWeight = '0.00';
        foreach ($weights as $weight) {
            $totalWeight = Money::add($totalWeight, $weight);
        }

        if (Money::compare($totalWeight, '0') !== 1) {
            return Money::split($amount, $count);
        }

        $allocated = [];
        $remaining = Money::of($amount);

        foreach ($weights as $index => $weight) {
            if ($index === $count - 1) {
                $allocated[] = $remaining;
                break;
            }

            $share = Money::round(bcmul($amount, bcdiv(Money::of($weight), $totalWeight, 8), 4));
            if (Money::compare($share, $remaining) === 1) {
                $share = $remaining;
            }
            $allocated[] = $share;
            $remaining = Money::sub($remaining, $share);
        }

        return $allocated;
    }
}
