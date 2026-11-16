<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'provider_ref',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
