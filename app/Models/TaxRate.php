<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'name',
        'rate_percent',
        'is_inclusive',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:3',
            'is_inclusive' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
