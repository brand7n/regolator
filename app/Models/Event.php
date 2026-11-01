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
 * @property array<array-key, mixed>|null $google_payload
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
        'google_payload' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'base_price' => 'integer',
    ];

    /**
     * A collision is suffixed here rather than thrown by the unique index: the
     * calendar import derives tags from titles, and a yearly "GOTENGAGED" is
     * normal. A moved `starts_at` deliberately does not re-suffix it, since the
     * calendar sync refreshes that column on every run.
     */
    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            if ($event->isDirty('event_tag') && $event->event_tag !== null) {
                $event->event_tag = $event->uniqueTag($event->event_tag);
            }
        });
    }

    /** Events are addressed by their tag. */
    public function getRouteKeyName(): string
    {
        return 'event_tag';
    }

    /** Rows with no tag (legacy imports) are still addressable by id. */
    public function getRouteKey(): mixed
    {
        return $this->event_tag ?? $this->getKey();
    }

    /**
     * Tags were lowercased and dash-separated in older mail, so match those
     * too, then fall back to the id for the `/events/{id}` links sent before.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== $this->getRouteKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        $value = (string) $value;
        $needle = mb_strtolower(str_replace(' ', '-', $value));

        return $this->whereRaw("lower(replace(event_tag, ' ', '-')) = ?", [$needle])->first()
            ?? (ctype_digit($value) ? $this->find((int) $value) : null);
    }

    /**
     * The tag has to be unique: a collision gets the start date and then a
     * counter: `GOTENGAGED-20260214-2`.
     */
    private function uniqueTag(string $tag): string
    {
        if (! $this->taken('event_tag', $tag)) {
            return $tag;
        }

        $dated = $this->suffixTag($tag, $this->starts_at->format('Ymd'));

        if (! $this->taken('event_tag', $dated)) {
            return $dated;
        }

        for ($n = 2; ; $n++) {
            $candidate = $this->suffixTag($dated, (string) $n);

            if (! $this->taken('event_tag', $candidate)) {
                return $candidate;
            }
        }
    }

    /** Keeps the tag under the length PayPal allows for a soft descriptor. */
    private function suffixTag(string $tag, string $suffix): string
    {
        $suffix = '-'.$suffix;

        return mb_substr($tag, 0, max(1, self::TAG_MAX_LENGTH - mb_strlen($suffix))).$suffix;
    }

    private function taken(string $column, string $value): bool
    {
        return self::query()
            ->where($column, $value)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

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

    /** The tag is sent as the PayPal soft descriptor, which allows this much. */
    private const TAG_MAX_LENGTH = 22;

    /** Assigning a freeform `kennel` string resolves it to a canonical kennel. */
    public function setAttribute($key, $value)
    {
        if ($key === 'kennel') {
            return parent::setAttribute('kennel_id', Kennel::resolve(is_string($value) ? $value : null)?->id);
        }

        if ($key === 'event_tag') {
            return parent::setAttribute($key, self::normalizeTag($value));
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Admins type tags freeform and the calendar import derives its own, so
     * both funnel through here: PayPal takes the tag as its soft descriptor
     * and silently fails order creation on anything outside `[A-Za-z0-9 .-]`
     * or longer than 22 characters.
     */
    private static function normalizeTag(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $tag = preg_replace('/[^A-Za-z0-9 .-]/', '', (string) $value) ?? '';
        $tag = rtrim(mb_substr(trim(preg_replace('/\s+/', ' ', $tag) ?? ''), 0, self::TAG_MAX_LENGTH));

        return $tag === '' ? null : $tag;
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

    /**
     * Past events that have uploaded tracks, narrowed by kennel and date range.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeWithTrails(Builder $query, ?int $kennelId = null, ?string $from = null, ?string $to = null): void
    {
        $query->past()
            ->has('tracks')
            ->when($kennelId, fn (Builder $query) => $query->where('kennel_id', $kennelId))
            ->when($from, fn (Builder $query) => $query->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('starts_at', '<=', Carbon::parse($to)->endOfDay()));
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
     * Whether `lat`/`lon` describe a real point (in range, and not the
     * [0, 0] "null island" that parks the map in the Atlantic).
     */
    public function hasCoordinates(): bool
    {
        if (! is_numeric($this->lat) || ! is_numeric($this->lon)) {
            return false;
        }

        $lat = (float) $this->lat;
        $lon = (float) $this->lon;

        return abs($lat) <= 90
            && abs($lon) <= 180
            && ! ($lat === 0.0 && $lon === 0.0);
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
