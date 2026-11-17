<?php

namespace Tests\Unit;

use App\Domain\Ordering\PricingEngine;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    public function test_exclusive_tax_fourteen_percent_on_one_hundred(): void
    {
        $quote = (new PricingEngine)->quote([
            [
                'name_ar' => 'منتج',
                'name_en' => 'Item',
                'quantity' => 1,
                'unit_price' => '100.00',
                'tax_rate_percent' => '14',
            ],
        ], pricesIncludeTax: false);

        $this->assertSame('100.00', $quote['subtotal']);
        $this->assertSame('14.00', $quote['tax_total']);
        $this->assertSame('114.00', $quote['total']);
        $this->assertSame('114.00', $quote['items'][0]['line_total']);
    }

    public function test_inclusive_tax_fourteen_percent_on_one_hundred_fourteen(): void
    {
        $quote = (new PricingEngine)->quote([
            [
                'name_ar' => 'منتج',
                'name_en' => 'Item',
                'quantity' => 1,
                'unit_price' => '114.00',
                'tax_rate_percent' => '14',
            ],
        ], pricesIncludeTax: true);

        $this->assertSame('100.00', $quote['subtotal']);
        $this->assertSame('14.00', $quote['tax_total']);
        $this->assertSame('114.00', $quote['total']);
        $this->assertSame('114.00', $quote['items'][0]['line_total']);
    }

    public function test_option_delta_ten_with_quantity_two(): void
    {
        $quote = (new PricingEngine)->quote([
            [
                'name_ar' => 'منتج',
                'name_en' => 'Item',
                'quantity' => 2,
                'unit_price' => '40.00',
                'tax_rate_percent' => '0',
                'options' => [
                    [
                        'name_ar' => 'كبير',
                        'name_en' => 'Large',
                        'price_delta' => '10.00',
                        'quantity' => 1,
                    ],
                ],
            ],
        ], pricesIncludeTax: false);

        $this->assertSame('10.00', $quote['items'][0]['options_total']);
        $this->assertSame('100.00', $quote['subtotal']);
        $this->assertSame('100.00', $quote['total']);
    }

    public function test_ten_percent_coupon_before_exclusive_tax(): void
    {
        $quote = (new PricingEngine)->quote(
            [
                [
                    'name_ar' => 'منتج',
                    'name_en' => 'Item',
                    'quantity' => 1,
                    'unit_price' => '100.00',
                    'tax_rate_percent' => '14',
                ],
            ],
            pricesIncludeTax: false,
            couponDiscount: [
                'kind' => 'coupon',
                'coupon_id' => 1,
                'label' => 'SAVE10',
                'amount' => '10.00',
            ],
        );

        $this->assertSame('10.00', $quote['discount_total']);
        $this->assertSame('90.00', $quote['subtotal']);
        $this->assertSame('12.60', $quote['tax_total']);
        $this->assertSame('102.60', $quote['total']);
    }

    public function test_delivery_fee_is_added_after_tax_and_is_not_taxed_by_default(): void
    {
        $quote = (new PricingEngine)->quote(
            [
                [
                    'name_ar' => 'منتج',
                    'name_en' => 'Item',
                    'quantity' => 1,
                    'unit_price' => '100.00',
                    'tax_rate_percent' => '14',
                ],
            ],
            pricesIncludeTax: false,
            deliveryFee: '25.00',
        );

        $this->assertSame('14.00', $quote['tax_total']);
        $this->assertSame('25.00', $quote['delivery_fee']);
        $this->assertSame('139.00', $quote['total']);
    }
}
