<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\TaxRate;

class SeedDefaultTaxRate
{
    public const NAME = 'ضريبة القيمة المضافة';

    public const RATE = '14.000';

    public function handle(): TaxRate
    {
        return TaxRate::query()->create([
            'name' => self::NAME,
            'rate_percent' => self::RATE,
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
