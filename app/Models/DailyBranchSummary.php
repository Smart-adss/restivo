<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBranchSummary extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'branch_id',
        'business_date',
        'orders_count',
        'gross_total',
        'discount_total',
        'tax_total',
        'net_total',
        'cash_total',
        'electronic_total',
        'refund_total',
        'delivery_fee_total',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'orders_count' => 'integer',
            'gross_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'cash_total' => 'decimal:2',
            'electronic_total' => 'decimal:2',
            'refund_total' => 'decimal:2',
            'delivery_fee_total' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
