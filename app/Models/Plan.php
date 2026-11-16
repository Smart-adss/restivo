<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'description',
        'is_active',
        'is_public',
        'trial_days',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')->withPivot('value');
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function priceFor(string $interval, string $currency = 'EGP'): ?string
    {
        $amount = $this->prices()->where('interval', $interval)->where('currency_code', $currency)->value('amount');

        return $amount === null ? null : (string) $amount;
    }
}
