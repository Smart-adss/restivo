<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'branch_id',
        'order_id',
        'station_id',
        'status',
        'started_at',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PrepStation::class, 'station_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class, 'ticket_id');
    }
}
