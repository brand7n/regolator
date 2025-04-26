<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $casts = [
        'properties' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function orders() : HasMany
    {
        return $this->hasMany(Order::class);
    }
}
