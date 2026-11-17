<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TableSession extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'table_id',
        'guest_count',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'table_session_orders', 'table_session_id', 'order_id')
            ->withPivot('tenant_id');
    }
}
