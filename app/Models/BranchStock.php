<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchStock extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $fillable = [
        'branch_id',
        'stock_item_id',
        'quantity',
        'reorder_level',
        'avg_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'avg_cost' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
