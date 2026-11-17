<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'guest_count',
        'reserved_at',
        'duration_minutes',
        'status',
        'notes',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'reserved_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(DiningTable::class, 'reservation_tables', 'reservation_id', 'table_id')
            ->withPivot('tenant_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class);
    }

    public function endsAt(): \Carbon\CarbonInterface
    {
        return $this->reserved_at->copy()->addMinutes($this->duration_minutes);
    }
}
