<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableTransfer extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'table_session_id',
        'from_table_id',
        'to_table_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function fromTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'from_table_id');
    }

    public function toTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'to_table_id');
    }
}
