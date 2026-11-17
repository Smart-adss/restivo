<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_item_id',
        'name_ar',
        'name_en',
        'price_delta',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'quantity' => 'decimal:3',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
