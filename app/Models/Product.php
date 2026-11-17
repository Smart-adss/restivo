<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Product extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'category_id',
        'tax_rate_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'base_price',
        'sku',
        'is_active',
        'prep_station_id',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(ProductBranchSetting::class);
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function branchSetting(?Branch $branch): ?ProductBranchSetting
    {
        if ($branch === null) {
            return null;
        }

        return $this->branchSettings->firstWhere('branch_id', $branch->id);
    }

    public function isAvailableAt(?Branch $branch): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $setting = $this->branchSetting($branch);

        return $setting === null ? true : $setting->is_available;
    }

    public function priceAt(?Branch $branch): string
    {
        $setting = $this->branchSetting($branch);

        if ($setting !== null && $setting->price_override !== null) {
            return (string) $setting->price_override;
        }

        return (string) $this->base_price;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function branchesMissingSettings(Collection $branches): Collection
    {
        $configured = $this->branchSettings->pluck('branch_id');

        return $branches->reject(fn (Branch $branch) => $configured->contains($branch->id))->values();
    }
}
