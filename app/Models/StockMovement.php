<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'stock_item_id',
        'kind',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'note',
        'user_id',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Stock movements are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Stock movements are append-only and cannot be deleted.');
        });
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
