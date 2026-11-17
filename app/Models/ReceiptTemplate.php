<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReceiptTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'header_text',
        'footer_text',
        'paper_width',
        'show_tax',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'paper_width' => 'integer',
            'show_tax' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
