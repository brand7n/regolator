<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;

/**
 * A GPX track uploaded by an attendee for an event.
 *
 * Two polylines are kept per track, both `[[lat, lng], ...]`, so the map never
 * re-parses the uploaded file: `points` is the detailed one (5 m) and
 * `points_overview` the coarse one (30 m) used until the user zooms in.
 *
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property string $name
 * @property string $path
 * @property int $size
 * @property array<int, array{0: float, 1: float}>|null $points
 * @property array<int, array{0: float, 1: float}>|null $points_overview
 * @property-read Event $event
 * @property-read User|null $user
 */
class EventTrack extends Model
{
    protected $guarded = [];

    /** Metres per degree of latitude at the equator. */
    private const METRES_PER_DEGREE = 111320.0;

    /** Tolerance (metres) for `points`: consumer GPS noise is 3–10 m. */
    private const DETAIL_TOLERANCE = 5.0;

    /** Tolerance (metres) for `points_overview`: sub-pixel at overview zooms. */
    private const OVERVIEW_TOLERANCE = 30.0;

    protected $casts = [
        'points' => 'array',
        'points_overview' => 'array',
        'size' => 'integer',
    ];

    /** Deleting a track always removes its GPX file from disk. */
    protected static function booted(): void
    {
        static::deleting(function (self $track): void {
            Storage::disk('public')->delete($track->path);
        });
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Extract a simplified polyline from GPX XML.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function pointsFrom(string $xml): array
    {
        // ponytail: reject entity declarations outright; LIBXML_NONET covers the rest.
        if (stripos($xml, '<!ENTITY') !== false) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $doc instanceof SimpleXMLElement) {
            return [];
        }

        $nodes = $doc->xpath('//*[local-name()="trkpt" or local-name()="rtept"]');

        $points = [];
        foreach (is_array($nodes) ? $nodes : [] as $node) {
            $lat = (float) (string) $node['lat'];
            $lon = (float) (string) $node['lon'];

            if (abs($lat) > 90 || abs($lon) > 180 || ($lat === 0.0 && $lon === 0.0)) {
                continue;
            }

            $points[] = [round($lat, 6), round($lon, 6)];
        }

        return self::simplify($points, self::DETAIL_TOLERANCE);
    }

    /**
     * Shape a set of events' tracks into what the Leaflet map consumes.
     *
     * Many-event maps ship the coarse tier only so a few hundred tracks fit on
     * one page; a single event ships both and the blade swaps tiers on zoom.
     *
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function mapPayload(Collection $events): array
    {
        $overview = $events->count() > 10;
        $tracks = [];

        foreach ($events as $event) {
            foreach ($event->tracks as $track) {
                $points = $track->points ?? [];

                if (count($points) < 2) {
                    continue;
                }

                $tracks[] = [
                    'id' => $track->id,
                    'event' => $event->event_tag ?: $event->name,
                    'name' => ($event->event_tag ?: $event->name).' — '.$track->user->name,
                    'points' => $track->points_overview ?? self::overview($points),
                    'detail' => $overview ? null : $points,
                    'date' => $event->starts_at->setTimezone('America/New_York')->format('M j, Y'),
                    'eventUrl' => route('events.show', $event),
                    'url' => Storage::url($track->path),
                ];
            }
        }

        return $tracks;
    }

    /**
     * Detailed (5 m) polylines for the given tracks, limited to events in scope.
     *
     * @param  SupportCollection<int, int>  $eventIds
     * @param  array<int, int|string>  $ids
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public static function detailPayload(SupportCollection $eventIds, array $ids): array
    {
        return self::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('id', array_slice(array_map('intval', $ids), 0, 100))
            ->get(['id', 'points'])
            ->mapWithKeys(fn (self $track) => [$track->id => $track->points ?? []])
            ->all();
    }

    /**
     * The coarse tier of a detailed polyline, for the overview map.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    public static function overview(array $points): array
    {
        return self::simplify($points, self::OVERVIEW_TOLERANCE);
    }

    /**
     * Ramer-Douglas-Peucker simplification with a fixed tolerance in metres.
     *
     * Point count is whatever the trail's shape needs: a collinear stretch
     * collapses to its endpoints however many fixes it had, a tight switchback
     * keeps its corners. No "every nth point" pass — sampling by index throws
     * corners away exactly where the trail is most interesting.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    public static function simplify(array $points, float $meters): array
    {
        $points = array_values($points);

        if (count($points) <= 2 || $meters <= 0) {
            return $points;
        }

        return self::rdp($points, $meters);
    }

    /**
     * Ramer-Douglas-Peucker, iteratively.
     *
     * The textbook version recurses once per split; on a few thousand points
     * that exhausts PHP's 128 MB limit before it finishes, so this walks a
     * stack of index ranges and never copies the point list.
     *
     * @param  array<int, array{0: float|int, 1: float|int}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function rdp(array $points, float $epsilon): array
    {
        $last = count($points) - 1;
        $keep = [0 => true, $last => true];
        $stack = [[0, $last]];

        while ($stack !== []) {
            [$first, $end] = array_pop($stack);

            $dmax = 0.0;
            $index = 0;

            for ($i = $first + 1; $i < $end; $i++) {
                $distance = self::distanceToLine($points[$i], $points[$first], $points[$end]);

                if ($distance > $dmax) {
                    $dmax = $distance;
                    $index = $i;
                }
            }

            if ($index !== 0 && $dmax >= $epsilon) {
                $keep[$index] = true;
                $stack[] = [$first, $index];
                $stack[] = [$index, $end];
            }
        }

        ksort($keep);

        $simplified = [];
        foreach (array_keys($keep) as $i) {
            $simplified[] = $points[$i];
        }

        return $simplified;
    }

    /**
     * Perpendicular distance in metres from $point to the $start–$end chord.
     *
     * Degree deltas are converted at the chord's latitude: ~111320 m per degree
     * of latitude, ~111320·cos(lat) m per degree of longitude.
     *
     * @param  array{0: float|int, 1: float|int}  $point
     * @param  array{0: float|int, 1: float|int}  $start
     * @param  array{0: float|int, 1: float|int}  $end
     */
    private static function distanceToLine(array $point, array $start, array $end): float
    {
        $latScale = self::METRES_PER_DEGREE;
        $lonScale = $latScale * cos(deg2rad($start[0]));

        $ux = ($end[1] - $start[1]) * $lonScale;
        $uy = ($end[0] - $start[0]) * $latScale;
        $vx = ($point[1] - $start[1]) * $lonScale;
        $vy = ($point[0] - $start[0]) * $latScale;

        $length = sqrt($ux * $ux + $uy * $uy);

        if ($length === 0.0) {
            return sqrt($vx * $vx + $vy * $vy);
        }

        // |cross(chord, point - start)| / |chord| — all three terms in metres.
        return abs($ux * $vy - $uy * $vx) / $length;
    }
}
