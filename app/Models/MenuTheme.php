<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

class MenuTheme extends Model
{
    use BelongsToTenant, HasPublicId;

    protected $fillable = [
        'name',
        'preset_key',
        'tokens',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
