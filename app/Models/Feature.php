<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'value_type',
        'name_ar',
        'name_en',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')->withPivot('value');
    }
}
