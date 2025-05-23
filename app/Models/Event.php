<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;
use App\Models\{User, Order};

class Event extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

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

    public function getOrder(User $user) : ?Order
    {
        return Order::where('user_id', $user->id)
            ->where('event_id', $this->id)
            ->first();
    }

    public function regoPaidAt(User $user) : ?Carbon
    {
        $order = $this->getOrder($user);
        if ($order && $order->status === OrderStatus::PaymentVerified) {
            return $order->verified_at;
        }
        return null;
    }
}
