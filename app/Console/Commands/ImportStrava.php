<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Matches Strava activities to events and stores them as `EventTrack` rows.
 *
 * Access is per-athlete (OAuth refresh token in .env), so this only sees
 * activities belonging to the configured Strava account. Run with
 * `--dry-run` first to eyeball the matches.
 */
class ImportStrava extends Command
{
    protected $signature = 'app:import-strava
        {--from= : Only consider activities after this date (default: 5 years ago)}
        {--event= : Only match against this event id}
        {--user= : Email of the user the tracks belong to (default: first admin)}
        {--delta= : Skip matches starting more than this many minutes from the event start}
        {--dry-run : Show matches without saving}
        {--skip-existing : Leave tracks that this Strava activity already imported untouched}';

    protected $description = 'Match Strava activities to events and store them as tracks';

    public function handle(): int
    {
        $token = $this->token();

        if (! $token) {
            $this->error('Strava token exchange failed. Check STRAVA_CLIENT_ID / STRAVA_CLIENT_SECRET / STRAVA_REFRESH_TOKEN.');

            return self::FAILURE;
        }

        $owner = $this->owner();

        if (! $owner) {
            $this->error('No user found to own the tracks. Pass --user=email.');

            return self::FAILURE;
        }

        $api = Http::withToken($token)->acceptJson()->timeout(30)->connectTimeout(10);
        $from = Carbon::parse($this->option('from') ?? now()->subYears(5)->toDateString());

        $this->info("Fetching Strava activities since {$from->toDateString()}...");
        $activities = $this->activities($api, $from);
        $this->line(count($activities).' activities fetched.');

        $events = $this->events($from);
        $this->line($events->count().' event(s) to check.');

        if ($events->isEmpty()) {
            $this->info('No events in that range.');

            return self::SUCCESS;
        }

        $rows = [];
        $used = [];
        $saved = 0;
        $limit = $this->option('delta') === null ? null : abs((int) $this->option('delta'));
        $skipped = 0;
        $existing = 0;

        foreach ($events as $event) {
            $match = $this->bestMatch($event, $activities, $used);

            if (! $match) {
                continue;
            }

            if ($limit !== null && abs($match[3]) > $limit) {
                $skipped++;

                continue;
            }

            [$activity, $score] = $match;

            if ($this->option('skip-existing') && $this->alreadyImported($event, $owner, $activity)) {
                $existing++;
                $this->line('  skip ['.$event->id.'] '.$event->name.' (already imported)');

                continue;
            }

            $used[$match[2]] = true;

            $this->line('  matched ['.$event->id.'] '.$event->name.' -> '.($activity['name'] ?? '?'));

            $points = $this->import($api, $event, $owner, $match, (bool) $this->option('dry-run'));

            $rows[] = [
                (string) $event->id,
                (string) $event->name,
                (string) ($activity['name'] ?? '(untitled)'),
                Carbon::parse($activity['start_date'])->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                (string) $match[3],
                $points === null ? '—' : (string) $points,
            ];

            $saved += $points === null ? 0 : 1;
        }

        if ($skipped > 0) {
            $this->warn("{$skipped} match(es) skipped: start time more than {$limit} min from the event.");
        }

        if ($rows === []) {
            $this->warn($existing > 0
                ? 'Nothing to import: every match was already imported (--skip-existing).'
                : 'No matching activities found.');

            return self::SUCCESS;
        }

        $this->table(['Id', 'Event', 'Activity', 'Start', 'Δt (min)', 'Pts'], $rows);

        if ($existing > 0) {
            $this->warn("{$existing} match(es) skipped: already imported (--skip-existing).");
        }

        $this->info($this->option('dry-run')
            ? 'Dry run: '.count($rows).' match(es), nothing saved.'
            : "Saved {$saved} of ".count($rows).' match(es).');

        return self::SUCCESS;
    }

    private function token(): ?string
    {
        $response = Http::asForm()->timeout(30)->connectTimeout(10)->post('https://www.strava.com/oauth/token', [
            'client_id' => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'refresh_token' => config('services.strava.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        return $response->successful() && is_string($response->json('access_token'))
            ? $response->json('access_token')
            : null;
    }

    /** @return array<int, mixed> */
    private function activities(PendingRequest $api, Carbon $from): array
    {
        $all = [];

        // ponytail: 10 pages = 2000 activities, Strava's daily read cap.
        for ($page = 1; $page <= 10; $page++) {
            $chunk = $api->get('https://www.strava.com/api/v3/athlete/activities', [
                'after' => $from->timestamp,
                'before' => now()->addDay()->timestamp,
                'page' => $page,
                'per_page' => 200,
            ])->throw()->json();

            if (! is_array($chunk) || $chunk === []) {
                break;
            }

            $all = [...$all, ...$chunk];

            if (count($chunk) < 200) {
                break;
            }
        }

        return array_values(array_filter($all, 'is_array'));
    }

    /** @return Collection<int, Event> */
    private function events(Carbon $from): Collection
    {
        $id = $this->option('event');

        return Event::query()
            ->with('kennel')
            ->when($id, fn ($q) => $q->whereKey($id))
            ->when(! $id, fn ($q) => $q->where('starts_at', '>=', $from))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Only activities recorded near the event are candidates; a matching run
     * number in the activity title wins, otherwise the closest start time does.
     *
     * @param  array<int, mixed>  $activities
     * @param  array<int, bool>  $used
     * @return array{0: array<string, mixed>, 1: int, 2: int, 3: int}|null
     */
    private function bestMatch(Event $event, array $activities, array $used): ?array
    {

        [$start, $end] = $this->window($event);

        $start = $start->copy()->subHours(12);
        $end = $end->copy()->addHours(12);

        $best = null;

        foreach ($activities as $key => $activity) {
            if (isset($used[$key]) || ! isset($activity['start_date'])) {
                continue;
            }

            $at = Carbon::parse($activity['start_date'])->utc();

            if ($at->lt($start) || $at->gt($end)) {
                continue;
            }

            $score = $this->score($event, $activity, $at);
            $delta = (int) round($start->copy()->addHours(12)->diffInMinutes($at, false));

            $closer = $best === null || abs($delta) < abs($best[3]);

            if ($best === null || $score > $best[1] || ($score === $best[1] && $closer)) {
                $best = [$activity, $score, (int) $key, $delta];
            }
        }

        return $best;
    }

    /**
     * Imported events hold New York wall-clock time in UTC columns, so the
     * calendar zone is re-attached before comparing to Strava's UTC stamps.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Event $event): array
    {
        $zone = (string) config('services.google.timezone', 'America/New_York');
        $stamp = fn (Carbon $t): string => $t->format('Y-m-d H:i:s');

        return [
            Carbon::parse($stamp($event->starts_at), $zone)->utc(),
            Carbon::parse($stamp($event->ends_at ?? $event->starts_at->copy()->addHours(6)), $zone)->utc(),
        ];
    }

    /** @param array<string, mixed> $activity */
    private function score(Event $event, array $activity, Carbon $at): int
    {
        $name = mb_strtolower((string) ($activity['name'] ?? ''));
        $score = 0;

        // "NVHHH #1317: HARE NEEDED" -> match the run number in the title.
        if (preg_match_all('/\d{2,5}/', (string) $event->name, $matches)) {
            foreach ($matches[0] as $number) {
                if (str_contains($name, $number)) {
                    $score += 100;
                    break;
                }
            }
        }

        if (str_contains($name, 'hash')) {
            $score += 20;
        }

        $kennel = $event->kennel;

        foreach (array_filter([$kennel?->abbreviation, $kennel?->full_name]) as $term) {
            if (str_contains($name, mb_strtolower($term))) {
                $score += 20;

                break;
            }
        }

        [$strictStart, $strictEnd] = $this->window($event);

        if ($at->gte($strictStart) && $at->lte($strictEnd)) {
            $score += 50;
        }

        return $score;
    }

    /**
     * A track counts as already imported when this event holds the file written
     * for that Strava activity — the activity id lives in the filename.
     *
     * @param  array<string, mixed>  $activity
     */
    private function alreadyImported(Event $event, User $owner, array $activity): bool
    {
        return EventTrack::where('event_id', $event->id)
            ->where('user_id', $owner->id)
            ->where('path', 'gpx/'.$event->id.'/strava-'.(int) ($activity['id'] ?? 0).'.gpx')
            ->exists();
    }

    /**
     * Fetch the latlng stream, write a GPX file, and store the track.
     *
     * @param  array{0: array<string, mixed>, 1: int, 2: int, 3: int}  $match
     * @return int|null point count, or null when nothing was saved
     */
    private function import(PendingRequest $api, Event $event, User $owner, array $match, bool $dryRun): ?int
    {
        $activity = $match[0];
        $activityId = (int) ($activity['id'] ?? 0);

        $payload = $api->get("https://www.strava.com/api/v3/activities/{$activityId}/streams", [
            'keys' => 'latlng',
            'key_by_type' => 'true',
        ])->throw()->json();

        $raw = $this->latlng($payload);

        if (count($raw) < 2) {
            return null;
        }

        $xml = $this->gpx((string) ($activity['name'] ?? 'Strava activity'), $raw);
        $points = EventTrack::pointsFrom($xml);

        if (count($points) < 2) {
            return null;
        }

        if ($dryRun) {
            return count($points);
        }

        $track = EventTrack::firstOrNew([
            'event_id' => $event->id,
            'user_id' => $owner->id,
        ]);

        $oldPath = $track->path;

        $track->fill([
            'name' => mb_substr((string) ($activity['name'] ?? 'Strava activity'), 0, 200),
            'path' => 'gpx/'.$event->id.'/strava-'.$activityId.'.gpx',
            'size' => strlen($xml),
            'points' => $points,
            'points_overview' => EventTrack::overview($points),
        ])->save();

        Storage::disk('public')->put($track->path, $xml);

        if ($oldPath && $oldPath !== $track->path) {
            Storage::disk('public')->delete($oldPath);
        }

        return count($points);
    }

    /**
     * Strava returns either `{'latlng': {'data': [...]}}` or `[{type, data}]`.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function latlng(mixed $payload): array
    {
        $data = null;

        if (is_array($payload) && isset($payload['latlng']['data'])) {
            $data = $payload['latlng']['data'];
        } elseif (is_array($payload)) {
            foreach ($payload as $stream) {
                if (is_array($stream) && ($stream['type'] ?? null) === 'latlng') {
                    $data = $stream['data'] ?? null;
                }
            }
        }

        $points = [];

        foreach (is_array($data) ? $data : [] as $pair) {
            if (! is_array($pair) || count($pair) < 2) {
                continue;
            }

            $points[] = [round((float) $pair[0], 6), round((float) $pair[1], 6)];
        }

        return $points;
    }

    /** @param array<int, array{0: float, 1: float}> $points */
    private function gpx(string $name, array $points): string
    {
        $trkpts = '';

        foreach ($points as [$lat, $lng]) {
            $trkpts .= '<trkpt lat="'.$lat.'" lon="'.$lng.'"></trkpt>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<gpx version="1.1" creator="Regolator" xmlns="http://www.topografix.com/GPX/1/1">'
            .'<trk><name>'.htmlspecialchars($name, ENT_XML1).'</name>'
            .'<trkseg>'.$trkpts.'</trkseg></trk></gpx>';
    }

    private function owner(): ?User
    {
        $email = $this->option('user');

        if (is_string($email) && $email !== '') {
            return User::where('email', $email)->first();
        }

        return User::where('is_admin', true)->orderBy('id')->first() ?? User::orderBy('id')->first();
    }
}
