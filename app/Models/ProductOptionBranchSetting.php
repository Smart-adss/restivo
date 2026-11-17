<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOptionBranchSetting extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $fillable = [
        'option_id',
        'branch_id',
        'is_available',
        'price_delta_override',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'price_delta_override' => 'decimal:2',
        ];
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
