<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'channel',
        'recipient',
        'template_key',
        'payload',
        'status',
        'provider',
        'provider_ref',
        'error',
        'dedupe_key',
        'sent_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
