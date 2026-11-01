<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Kennel;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Pulls events from a public Google Calendar using a plain API key
 * (read-only, no OAuth). Run manually: `php artisan app:sync-google-calendar`.
 *
 * Upserts on `events.google_event_id`, so re-running is safe and admin edits to
 * name/description/location/dates are preserved. `created_by` is refreshed from
 * the calendar's `creator` on every run.
 */
class SyncGoogleCalendar extends Command
{
    protected $signature = 'app:sync-google-calendar
        {--from= : Only import events ending after this date (default: 5 years ago)}
        {--kennel= : Kennel to assign to newly imported events}
        {--user= : Email of the user that will own imported events (default: first admin)}';

    protected $description = 'Import past and upcoming events from a Google Calendar';

    public function handle(): int
    {
        $key = config('services.google.calendar_api_key');
        $calendar = config('services.google.calendar_id');

        if (! is_string($key) || $key === '' || ! is_string($calendar) || $calendar === '') {
            $this->error('Set GOOGLE_CALENDAR_API_KEY and GOOGLE_CALENDAR_ID first.');

            return self::FAILURE;
        }

        $owner = $this->owner();

        if (! $owner) {
            $this->error('No user found to own imported events. Pass --user=email.');

            return self::FAILURE;
        }

        $kennel = Kennel::resolve((string) ($this->option('kennel') ?? config('services.google.kennel')));
        $from = Carbon::parse($this->option('from') ?? now()->subYears(5)->toDateString());

        $created = 0;
        $updated = 0;
        $pageToken = null;

        do {
            $payload = Http::get(
                'https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendar).'/events',
                array_filter([
                    'key' => $key,
                    'timeMin' => $from->toRfc3339String(),
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'maxResults' => 250,
                    'pageToken' => $pageToken,
                ])
            )->throw()->json();

            $items = is_array($payload) && is_array($payload['items'] ?? null) ? $payload['items'] : [];

            foreach ($items as $item) {
                if (! is_array($item) || ($item['status'] ?? 'confirmed') === 'cancelled') {
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

                $event->fill([
                    'name' => $name,
                    'description' => (string) ($item['description'] ?? ''),
                    'location' => (string) ($item['location'] ?? ''),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'google_payload' => $item,
                ]);

                // Rows imported before tags were derived from the title still
                // carry the calendar id; refresh those, but never a manual edit.
                if ($event->exists && $event->event_tag === $event->google_event_id) {
                    $event->event_tag = $this->tag($name, $externalId, $kennel->abbreviation);
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

                $event->wasRecentlyCreated ? $created++ : $updated++;
            }

            $pageToken = is_array($payload) && is_string($payload['nextPageToken'] ?? null)
                ? $payload['nextPageToken']
                : null;
        } while ($pageToken);

        $this->info("Imported {$created} new event(s), updated {$updated}.");

        return self::SUCCESS;
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
