<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'name_ar',
        'name_en',
        'abbreviation',
        'kind',
    ];

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
