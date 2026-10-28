<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical kennel. Events point here through `events.kennel_id`, so freeform
 * input ("NVHHH", "Nittany Valley Hash House Harriers") collapses into one row.
 *
 * @property int $id
 * @property string $name
 * @property-read Collection<int, Event> $events
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel whereName($value)
 */
class Kennel extends Model
{
    protected $guarded = [];

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Resolve a freeform kennel name to its record, creating it when new.
     *
     * Matching ignores case and repeated whitespace so "nvhhh " reuses "NVHHH".
     * ponytail: no alias table — rename/merge via the admin form until it hurts.
     */
    public static function resolve(?string $name): ?self
    {
        $normalized = self::normalize($name);

        if ($normalized === null) {
            return null;
        }

        return self::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($normalized)])
            ->first()
            ?? self::create(['name' => $normalized]);
    }

    public static function normalize(?string $name): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $normalized === '' ? null : $normalized;
    }
}
