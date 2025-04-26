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
        'base_price' => 'integer',
    ];

    // Accessor: get base_price in dollars
    public function getBasePriceInDollarsAttribute()
    {
        return number_format($this->base_price / 100, 2, '.', '');
    }

    // Mutator: set base_price from dollars
    public function setBasePriceInDollarsAttribute($value)
    {
        $this->base_price = (int) round($value * 100);
    }

    public function orders() : HasMany
    {
        return $this->hasMany(Order::class);
    }
}
