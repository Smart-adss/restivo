<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOrderSequence extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $primaryKey = 'branch_id';

    public $incrementing = false;

    protected $fillable = [
        'branch_id',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
