<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'branch_id',
        'name_ar',
        'name_en',
        'fee',
        'min_order',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'min_order' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
