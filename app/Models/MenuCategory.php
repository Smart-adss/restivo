<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'parent_id',
        'name_ar',
        'name_en',
        'sort_order',
        'is_active',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeVisibleOnPublicMenu(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $products) => $products->where('is_active', true));
    }
}
