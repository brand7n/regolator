<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Canonical kennel: a short abbreviation ("NVHHH") plus the long name
 * ("Nittany Valley Hash House Harriers"). Events point here through
 * `events.kennel_id`, so freeform input collapses into one row either way.
 *
 * @property int $id
 * @property string $abbreviation
 * @property string|null $full_name
 * @property-read Collection<int, Event> $events
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kennel whereAbbreviation($value)
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
     * Matching ignores case and repeated whitespace, and accepts either the
     * abbreviation ("nvhhh") or the full name ("Nittany Valley Hash House
     * Harriers"), so both spellings reuse the same row.
     * ponytail: no alias table — rename/merge via the admin form until it hurts.
     */
    public static function resolve(?string $name): ?self
    {
        $normalized = self::normalize($name);

        if ($normalized === null) {
            return null;
        }

        $lower = mb_strtolower($normalized);

        return self::query()
            ->where(fn (Builder $query) => $query
                ->whereRaw('lower(abbreviation) = ?', [$lower])
                ->orWhereRaw('lower(full_name) = ?', [$lower]))
            ->first()
            ?? self::create(['abbreviation' => $normalized]);
    }

    /** The long name when one is known, otherwise the abbreviation. */
    public function label(): string
    {
        return (string) ($this->full_name ?: $this->abbreviation);
    }

    public static function normalize(?string $name): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $normalized === '' ? null : $normalized;
    }
}
