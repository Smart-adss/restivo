<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplitPart extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'split_id',
        'label',
        'amount',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function split(): BelongsTo
    {
        return $this->belongsTo(BillSplit::class, 'split_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
