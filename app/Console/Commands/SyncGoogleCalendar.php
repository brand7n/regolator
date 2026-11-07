<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Kennel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls events from a public Google Calendar using a plain API key
 * (read-only, no OAuth). The first run reads the whole calendar; every later
 * run sends the `syncToken` from the previous one and receives only what
 * changed since.
 *
 * Upserts on `events.google_event_id`, so re-running is safe. An item is only
 * written back when its `etag` differs from the one already stored, so a full
 * re-read leaves untouched events (and admin edits to them) alone. When the
 * calendar item itself did change, name/description/location/starts_at/ends_at/
 * created_by are overwritten from Google; kennel_id, base_price, max_regos, the
 * track rows, and the tag (once it differs from the calendar id) are not.
 */
class SyncGoogleCalendar extends Command
{
    private const TOKEN_KEY = 'google-calendar-sync-token';

    protected $signature = 'app:sync-google-calendar
        {--kennel= : Kennel to assign to newly imported events}
        {--user= : Email of the user that will own imported events (default: first admin)}';

    protected $description = 'Import events from a Google Calendar';

    public function handle(): int
    {
        $key = config('services.google.calendar_api_key');
        $calendar = config('services.google.calendar_id');

        if (! is_string($key) || $key === '' || ! is_string($calendar) || $calendar === '') {
            $this->error('Set GOOGLE_CALENDAR_API_KEY and GOOGLE_CALENDAR_ID first.');
            Log::error('google calendar sync: GOOGLE_CALENDAR_API_KEY / GOOGLE_CALENDAR_ID not set');

            return self::FAILURE;
        }

        $owner = $this->owner();

        if (! $owner) {
            $this->error('No user found to own imported events. Pass --user=email.');
            Log::error('google calendar sync: no user found to own imported events');

            return self::FAILURE;
        }

        $kennel = Kennel::resolve((string) ($this->option('kennel') ?? config('services.google.kennel')));

        $cached = Cache::get(self::TOKEN_KEY);
        $token = is_string($cached) && $cached !== '' ? $cached : null;

        while (true) {
            try {
                $result = $this->walk($key, $calendar, $token, $kennel, $owner);

                break;
            } catch (RequestException $exception) {
                // 410 GONE: the token went stale (expired, or the calendar id
                // changed). Fall back to one full read.
                if ($token === null || $exception->response->status() !== 410) {
                    throw $exception;
                }

                $token = null;
                Cache::forget(self::TOKEN_KEY);
                $this->warn('Sync token expired; re-reading the whole calendar.');
                Log::warning('google calendar sync: sync token expired, re-reading the whole calendar');
            }
        }

        if ($result['syncToken'] !== null) {
            Cache::forever(self::TOKEN_KEY, $result['syncToken']);
        }

        $this->info("Imported {$result['created']} new event(s), updated {$result['updated']}.");
        Log::info('google calendar sync: imported events', [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'cancelled' => $result['cancelled'],
        ]);

        if ($result['cancelled'] > 0) {
            $this->warn("{$result['cancelled']} event(s) are cancelled in the calendar; left in place.");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{created: int, updated: int, cancelled: int, syncToken: ?string}
     */
    private function walk(string $key, string $calendar, ?string $syncToken, ?Kennel $kennel, User $owner): array
    {
        $created = 0;
        $updated = 0;
        $cancelled = 0;
        $pageToken = null;
        $nextSyncToken = null;

        do {
            $payload = Http::get(
                'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendar).'/events',
                array_filter([
                    'key' => $key,
                    // Identical on every request on purpose: changing any query
                    // parameter invalidates the token, and `timeMin`/`orderBy`
                    // cannot be combined with one at all.
                    'singleEvents' => 'true',
                    'maxResults' => 250,
                    'syncToken' => $syncToken,
                    'pageToken' => $pageToken,
                ], fn (mixed $value): bool => $value !== null)
            )->throw()->json();

            $items = is_array($payload) && is_array($payload['items'] ?? null) ? $payload['items'] : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                // Delta reads report deletions as bare tombstones. Deleting the
                // local event is not what we do (it may own regos and tracks).
                if (($item['status'] ?? 'confirmed') === 'cancelled') {
                    $cancelled++;

                    continue;
                }

                $externalId = (string) ($item['id'] ?? '');
                $name = trim((string) ($item['summary'] ?? ''));
                $startsAt = $this->moment($item['start'] ?? null);
                $endsAt = $this->moment($item['end'] ?? null);

                if ($externalId === '' || $name === '' || ! $startsAt || ! $endsAt) {
                    continue;
                }

                $event = Event::firstOrNew(['google_event_id' => $externalId]);

                if (! $event->exists) {
                    $event->kennel_id = $kennel?->id;
                    $event->event_tag = $this->tag($name, $externalId, $kennel?->abbreviation);
                    $event->base_price = 0;
                    $event->max_regos = 0;
                    $event->private = 0;
                }

                // Same etag as the copy we already stored: the calendar item
                // did not change, so leave the row (and any admin edit to it)
                // alone. Only matters when the whole calendar is replayed —
                // a delta only carries items Google says have changed.
                $etag = $item['etag'] ?? null;
                $stored = is_array($event->google_payload) ? ($event->google_payload['etag'] ?? null) : null;
                $unchanged = $event->exists && is_string($etag) && $etag !== '' && $etag === $stored;

                if (! $unchanged) {
                    $event->fill([
                        'name' => $name,
                        'description' => (string) ($item['description'] ?? ''),
                        'location' => (string) ($item['location'] ?? ''),
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'google_payload' => $item,
                    ]);
                }

                // Rows imported before tags were derived from the title still
                // carry the calendar id; refresh those, but never a manual edit.
                if ($event->exists && $event->event_tag === $event->google_event_id) {
                    $event->event_tag = $this->tag($name, $externalId, $kennel?->abbreviation);
                }

                // Refreshed on every run so re-syncing backfills existing rows.
                // ponytail: a manual admin reassignment is overwritten when the
                // calendar creator has an account; needs a "locked" flag to keep it.
                if ($creatorId = $this->creatorId($item['creator'] ?? null)) {
                    $event->created_by = $creatorId;
                } elseif (! $event->exists) {
                    $event->created_by = $owner->id;
                }

                $event->save();

                if ($event->wasRecentlyCreated) {
                    $created++;
                } elseif (! $unchanged) {
                    $updated++;
                }
            }

            $nextSyncToken = is_array($payload) && is_string($payload['nextSyncToken'] ?? null)
                ? $payload['nextSyncToken']
                : null;

            $pageToken = is_array($payload) && is_string($payload['nextPageToken'] ?? null)
                ? $payload['nextPageToken']
                : null;
        } while ($pageToken);

        return [
            'created' => $created,
            'updated' => $updated,
            'cancelled' => $cancelled,
            'syncToken' => $nextSyncToken === '' ? null : $nextSyncToken,
        ];
    }

    /**
     * Derive an event tag from the calendar title: "NVHHH #1317: HARE NEEDED"
     * becomes "NVHHH1317". Everything after the first separator is dropped
     * (it is usually status noise) and the rest is reduced to A-Z0-9, since the
     * tag ends up in a PayPal purchase description. The model then clamps it
     * to the length PayPal accepts.
     *
     * Imported tags always start with the kennel abbreviation: one carried
     * later in the title ("Red Dress Run NVHHH 1318") is moved to the front,
     * one missing entirely ("Red Dress Run") is prepended. A title with no
     * usable words left ("🍺") falls back to the calendar id, which the model's
     * usual collision handling keeps unique.
     */
    private function tag(string $name, string $fallback, ?string $kennel): string
    {
        $head = preg_split('/[:\x{2013}\x{2014}|]/u', $name)[0] ?? $name;
        $tag = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $head) ?? '', 'UTF-8');
        $tag = $tag === '' ? $fallback : $tag;
        $prefix = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $kennel) ?? '', 'UTF-8');

        if ($prefix === '') {
            return $tag;
        }

        return $prefix.str_replace($prefix, '', $tag);
    }

    private function moment(mixed $value): ?Carbon
    {
        if (! is_array($value)) {
            return null;
        }

        $raw = $value['dateTime'] ?? $value['date'] ?? null;

        return is_string($raw) && $raw !== '' ? Carbon::parse($raw) : null;
    }

    /**
     * `creator.email` is the best guess at who raised the event. It is not
     * always the hare, but it beats defaulting everything to one admin.
     */
    private function creatorId(mixed $value): ?int
    {
        $email = is_array($value) ? ($value['email'] ?? null) : null;

        return is_string($email) && $email !== ''
            ? User::where('email', $email)->value('id')
            : null;
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
