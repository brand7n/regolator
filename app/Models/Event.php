<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $name
 * @property string $kennel
 * @property string $description
 * @property string|null $event_photo_path
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $location
 * @property array<array-key, mixed>|null $properties
 * @property int $created_by
 * @property int $private
 * @property string|null $event_tag
 * @property int $base_price
 * @property string|null $lat
 * @property string|null $lon
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property mixed $base_price_in_dollars
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereBasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEventPhotoPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereEventTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereKennel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereLon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event wherePrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Event extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'base_price' => 'integer',
    ];

    // Accessor: get base_price in dollars
    public function getBasePriceInDollarsAttribute(): string
    {
        return number_format($this->base_price / 100, 2, '.', '');
    }

    // Mutator: set base_price from dollars
    public function setBasePriceInDollarsAttribute(float|string $value): void
    {
        $this->base_price = (int) round($value * 100);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getOrder(User $user): ?Order
    {
        return Order::where('user_id', $user->id)
            ->where('event_id', $this->id)
            ->first();
    }

    public function regoPaidAt(User $user): ?Carbon
    {
        $order = $this->getOrder($user);
        if ($order && $order->status === OrderStatus::PaymentVerified) {
            return $order->verified_at;
        }

        return null;
    }
}
