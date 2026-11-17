<?php

namespace Tests\Unit;

use App\Support\Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_split_one_hundred_across_three_parts_sums_exactly(): void
    {
        $parts = Money::split('100.00', 3);

        $this->assertSame(['33.33', '33.33', '33.34'], $parts);
        $this->assertSame('100.00', Money::add(...$parts));
    }

    public function test_extract_inclusive_tax_at_fourteen_percent(): void
    {
        $parts = Money::extractInclusiveTax('114.00', '14');

        $this->assertSame('100.00', $parts['net']);
        $this->assertSame('14.00', $parts['tax']);
    }
}
