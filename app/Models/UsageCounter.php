<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'feature_key',
        'period_start',
        'period_end',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
