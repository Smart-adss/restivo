<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBranchSetting extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'branch_id',
        'is_available',
        'price_override',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'price_override' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
