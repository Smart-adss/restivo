<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'branch_id',
        'area_id',
        'name',
        'seats',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(DiningArea::class, 'area_id');
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_tables', 'table_id', 'reservation_id')
            ->withPivot('tenant_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'table_id');
    }

    public function openSession(): ?TableSession
    {
        return $this->sessions()->where('status', 'open')->latest('id')->first();
    }
}
