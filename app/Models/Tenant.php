<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasPublicId;

    protected $fillable = [
        'slug',
        'legal_name',
        'display_name_ar',
        'display_name_en',
        'status',
        'timezone',
        'currency_code',
        'default_locale',
        'email',
        'phone',
        'logo_path',
        'credit_balance',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:2',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' ? $this->display_name_ar : $this->display_name_en;
    }
}
