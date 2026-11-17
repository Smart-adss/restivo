<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Subscription\AuditLogger;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTaxRate
{
    public function __construct(
        private FeatureGate $features,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, rate_percent: numeric-string|float|int, is_inclusive: bool, is_default: bool, is_active: bool}  $data
     */
    public function create(array $data): TaxRate
    {
        return DB::transaction(function () use ($data): TaxRate {
            $this->features->assertWritable();
            $data = $this->normalize($data, null);
            $this->assertDefaultStaysActive($data);

            if ($data['is_default']) {
                TaxRate::query()->update(['is_default' => false]);
            }

            $rate = TaxRate::query()->create($data);
            $this->syncOrderSetting($rate);
            $this->audit->log('tax_rate.saved', $rate, null, $this->snapshot($rate));

            return $rate;
        });
    }

    /**
     * @param  array{name: string, rate_percent: numeric-string|float|int, is_inclusive: bool, is_default: bool, is_active: bool}  $data
     */
    public function update(TaxRate $rate, array $data): TaxRate
    {
        return DB::transaction(function () use ($rate, $data): TaxRate {
            $this->features->assertWritable();
            $before = $this->snapshot($rate);
            $data = $this->normalize($data, $rate);

            if ($rate->is_default && ! $data['is_default']) {
                throw ValidationException::withMessages([
                    'is_default' => __('app.tax_default_required'),
                ]);
            }

            $this->assertDefaultStaysActive($data);

            if ($data['is_default']) {
                TaxRate::query()->whereKeyNot($rate->id)->update(['is_default' => false]);
            }

            $rate->fill($data)->save();
            $this->syncOrderSetting($rate->fresh());
            $this->audit->log('tax_rate.saved', $rate, $before, $this->snapshot($rate->fresh()));

            return $rate;
        });
    }

    /**
     * @param  array{name: string, rate_percent: numeric-string|float|int, is_inclusive: bool, is_default: bool, is_active: bool}  $data
     * @return array{name: string, rate_percent: string, is_inclusive: bool, is_default: bool, is_active: bool}
     */
    private function normalize(array $data, ?TaxRate $current): array
    {
        $isDefault = (bool) $data['is_default'];

        if ($current === null && ! $isDefault && ! TaxRate::query()->where('is_default', true)->exists()) {
            $isDefault = true;
        }

        return [
            'name' => $data['name'],
            'rate_percent' => number_format((float) $data['rate_percent'], 3, '.', ''),
            'is_inclusive' => (bool) $data['is_inclusive'],
            'is_default' => $isDefault,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /**
     * @param  array{is_default: bool, is_active: bool}  $data
     */
    private function assertDefaultStaysActive(array $data): void
    {
        if ($data['is_default'] && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => __('app.tax_default_must_stay_active'),
            ]);
        }
    }

    private function syncOrderSetting(TaxRate $rate): void
    {
        if (! $rate->is_default) {
            return;
        }

        DB::table('tenant_settings')->updateOrInsert(
            [
                'tenant_id' => app(TenantContext::class)->id(),
                'key' => 'tax.prices_include_tax',
            ],
            [
                'value' => json_encode($rate->is_inclusive),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TaxRate $rate): array
    {
        return [
            'name' => $rate->name,
            'rate_percent' => (string) $rate->rate_percent,
            'is_inclusive' => $rate->is_inclusive,
            'is_default' => $rate->is_default,
            'is_active' => $rate->is_active,
        ];
    }
}
