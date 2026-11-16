<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'interval',
        'currency_code',
        'amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
