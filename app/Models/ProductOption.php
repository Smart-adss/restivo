<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'group_id',
        'name_ar',
        'name_en',
        'price_delta',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'group_id');
    }

    public function branchSettings(): HasMany
    {
        return $this->hasMany(ProductOptionBranchSetting::class, 'option_id');
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function branchSetting(?Branch $branch): ?ProductOptionBranchSetting
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

    public function priceDeltaAt(?Branch $branch): string
    {
        $setting = $this->branchSetting($branch);

        if ($setting !== null && $setting->price_delta_override !== null) {
            return (string) $setting->price_delta_override;
        }

        return (string) $this->price_delta;
    }
}
