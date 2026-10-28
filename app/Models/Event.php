<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $kennel_id
 * @property-read Kennel|null $kennel
 * @property string $description
 * @property string|null $event_photo_path
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $location
 * @property array<array-key, mixed>|null $properties
 * @property int $created_by
 * @property string|null $google_event_id
 * @property int $private
 * @property string|null $event_tag
 * @property int $base_price
 * @property int $max_regos
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Event whereKennelId($value)
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
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "event {$eventName}: {$this->name}");
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

    /** Assigning a freeform `kennel` string resolves it to a canonical kennel. */
    public function setAttribute($key, $value)
    {
        if ($key === 'kennel') {
            return parent::setAttribute('kennel_id', Kennel::resolve(is_string($value) ? $value : null)?->id);
        }

        return parent::setAttribute($key, $value);
    }

    /** @return BelongsTo<Kennel, $this> */
    public function kennel(): BelongsTo
    {
        return $this->belongsTo(Kennel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<EventTrack, $this> */
    public function tracks(): HasMany
    {
        return $this->hasMany(EventTrack::class);
    }

    /** @param Builder<Event> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('ends_at', '>=', now());
    }

    /** @param Builder<Event> $query */
    public function scopePast(Builder $query): void
    {
        $query->where('ends_at', '<', now());
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

    /**
     * Count unique paid registrants using the same algorithm as Regos::render().
     */
    public function paidRegosCount(): int
    {
        return $this->orders()
            ->where('status', OrderStatus::PaymentVerified)
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Check if the event has reached its max registrations limit.
     * 0 means unlimited.
     */
    public function isFull(): bool
    {
        return $this->max_regos > 0 && $this->paidRegosCount() >= $this->max_regos;
    }
}
