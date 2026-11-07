<?php

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    config([
        'services.google.calendar_api_key' => 'API_KEY',
        'services.google.calendar_id' => 'kennel@group.calendar.google.com',
        'services.google.kennel' => 'NVHHH',
    ]);
});

test('imports past and upcoming events from the calendar', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'past-1',
                    'summary' => 'NVHHH 1900',
                    'description' => 'The original.',
                    'location' => 'Somewhere, PA',
                    'start' => ['dateTime' => '2019-05-04T18:00:00-04:00'],
                    'end' => ['dateTime' => '2019-05-05T12:00:00-04:00'],
                ],
                [
                    'id' => 'future-1',
                    'summary' => 'NVHHH 2000',
                    'start' => ['date' => '2027-05-01'],
                    'end' => ['date' => '2027-05-02'],
                ],
                [
                    'id' => 'cancelled-1',
                    'summary' => 'Cancelled Hash',
                    'status' => 'cancelled',
                    'start' => ['dateTime' => '2020-01-01T18:00:00-05:00'],
                    'end' => ['dateTime' => '2020-01-01T20:00:00-05:00'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $past = Event::where('google_event_id', 'past-1')->firstOrFail();
    expect($past->name)->toBe('NVHHH 1900')
        ->and($past->event_tag)->toBe('NVHHH1900')
        ->and($past->location)->toBe('Somewhere, PA')
        ->and($past->description)->toBe('The original.')
        ->and($past->created_by)->toBe($this->admin->id)
        ->and($past->kennel?->abbreviation)->toBe('NVHHH')
        ->and($past->ends_at->lt(now()))->toBeTrue()
        ->and($past->google_payload['id'])->toBe('past-1')
        ->and($past->google_payload['start']['dateTime'])->toBe('2019-05-04T18:00:00-04:00');

    expect(Event::where('google_event_id', 'future-1')->exists())->toBeTrue()
        ->and(Event::where('google_event_id', 'cancelled-1')->exists())->toBeFalse();
});

test('re-running updates existing events instead of duplicating', function () {
    Http::fakeSequence()
        ->push(['items' => [[
            'id' => 'evo-1',
            'summary' => 'Before',
            'start' => ['dateTime' => '2021-05-04T18:00:00-04:00'],
            'end' => ['dateTime' => '2021-05-05T12:00:00-04:00'],
        ]]])
        ->push(['items' => [[
            'id' => 'evo-1',
            'summary' => 'After',
            'start' => ['dateTime' => '2021-05-04T18:00:00-04:00'],
            'end' => ['dateTime' => '2021-05-05T12:00:00-04:00'],
        ]]]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();
    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::where('google_event_id', 'evo-1')->count())->toBe(1)
        ->and(Event::where('google_event_id', 'evo-1')->value('name'))->toBe('After');
});

test('follows pagination tokens', function () {
    Http::fake([
        'www.googleapis.com/*pageToken=page2*' => Http::response(['items' => [[
            'id' => 'page-2',
            'summary' => 'Second page',
            'start' => ['dateTime' => '2021-06-04T18:00:00-04:00'],
            'end' => ['dateTime' => '2021-06-05T12:00:00-04:00'],
        ]]]),
        'www.googleapis.com/*' => Http::response([
            'nextPageToken' => 'page2',
            'items' => [[
                'id' => 'page-1',
                'summary' => 'First page',
                'start' => ['dateTime' => '2021-05-04T18:00:00-04:00'],
                'end' => ['dateTime' => '2021-05-05T12:00:00-04:00'],
            ]],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::whereIn('google_event_id', ['page-1', 'page-2'])->count())->toBe(2);
});

test('fails without calendar credentials', function () {
    config(['services.google.calendar_api_key' => null]);

    $this->artisan('app:sync-google-calendar')->assertFailed();

    Http::assertNothingSent();
});

test('imported events are owned by the calendar creator when they have an account', function () {
    $hare = User::create([
        'name' => 'Jason McKenzie',
        'email' => 'jcmckenz@gmail.com',
        'password' => 'secret',
    ]);

    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'creator-1',
                    'summary' => 'NVHHH 1317',
                    'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
                    'creator' => ['email' => 'jcmckenz@gmail.com', 'displayName' => 'Jason McKenzie'],
                    'organizer' => ['email' => 'kennel@group.calendar.google.com'],
                ],
                [
                    'id' => 'creator-2',
                    'summary' => 'NVHHH 1318',
                    'start' => ['dateTime' => '2026-11-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-14T21:00:00-05:00'],
                    'creator' => ['email' => 'stranger@example.com'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::where('google_event_id', 'creator-1')->firstOrFail()->created_by)->toBe($hare->id)
        ->and(Event::where('google_event_id', 'creator-2')->firstOrFail()->created_by)->toBe($this->admin->id);
});

test('re-running backfills the creator on events that already exist', function () {
    $existing = Event::create([
        'name' => 'NVHHH 1317',
        'description' => '',
        'location' => '',
        'starts_at' => '2026-11-07 18:00:00',
        'ends_at' => '2026-11-07 21:00:00',
        'google_event_id' => 'backfill-1',
        'created_by' => $this->admin->id,
        'base_price' => 0,
        'max_regos' => 0,
        'private' => false,
    ]);

    $hare = User::create([
        'name' => 'Jason McKenzie',
        'email' => 'jcmckenz@gmail.com',
        'password' => 'secret',
    ]);

    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'backfill-1',
                'summary' => 'NVHHH #1317: HARE NEEDED',
                'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
                'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
                'creator' => ['email' => 'jcmckenz@gmail.com'],
            ]],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $existing->refresh();

    expect($existing->created_by)->toBe($hare->id)
        ->and($existing->name)->toBe('NVHHH #1317: HARE NEEDED')
        ->and(Event::count())->toBe(1);
});

test('tags are derived from the event title', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'tag-1',
                    'summary' => 'NVHHH #1317: HARE NEEDED',
                    'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
                ],
                [
                    'id' => 'tag-2',
                    'summary' => 'Red Dress Run — NVHHH 1318',
                    'start' => ['dateTime' => '2026-11-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-14T21:00:00-05:00'],
                ],
                [
                    'id' => 'tag-3',
                    'summary' => '🍺',
                    'start' => ['dateTime' => '2026-11-21T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-21T21:00:00-05:00'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::where('google_event_id', 'tag-1')->value('event_tag'))->toBe('NVHHH1317')
        ->and(Event::where('google_event_id', 'tag-2')->value('event_tag'))->toBe('NVHHHREDDRESSRUN')
        ->and(Event::where('google_event_id', 'tag-3')->value('event_tag'))->toBe('NVHHHtag-3');
});

test('a kennel abbreviation outside the title head is moved to the front', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'mid-1',
                    'summary' => 'Red Dress Run NVHHH 1318',
                    'start' => ['dateTime' => '2026-11-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-14T21:00:00-05:00'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::where('google_event_id', 'mid-1')->value('event_tag'))->toBe('NVHHHREDDRESSRUN1318');
});

test('re-syncing replaces a calendar-id tag but keeps a manual one', function () {
    $legacy = Event::create([
        'name' => 'NVHHH 1317',
        'description' => '',
        'location' => '',
        'starts_at' => '2026-11-07 18:00:00',
        'ends_at' => '2026-11-07 21:00:00',
        'google_event_id' => 'retag-1',
        'event_tag' => 'retag-1',
        'created_by' => $this->admin->id,
        'base_price' => 0,
        'max_regos' => 0,
        'private' => false,
    ]);

    $manual = Event::create([
        'name' => 'NVHHH 1318',
        'description' => '',
        'location' => '',
        'starts_at' => '2026-11-14 18:00:00',
        'ends_at' => '2026-11-14 21:00:00',
        'google_event_id' => 'retag-2',
        'event_tag' => 'CUSTOM',
        'created_by' => $this->admin->id,
        'base_price' => 0,
        'max_regos' => 0,
        'private' => false,
    ]);

    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'retag-1',
                    'summary' => 'NVHHH #1317: HARE NEEDED',
                    'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
                ],
                [
                    'id' => 'retag-2',
                    'summary' => 'NVHHH #1318: HARE NEEDED',
                    'start' => ['dateTime' => '2026-11-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-14T21:00:00-05:00'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect($legacy->refresh()->event_tag)->toBe('NVHHH1317')
        ->and($manual->refresh()->event_tag)->toBe('CUSTOM');
});

test('two calendar events with the same title get distinct tags', function () {
    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'dupe-1',
                    'summary' => 'GOTENGAGED',
                    'start' => ['dateTime' => '2026-02-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-02-14T21:00:00-05:00'],
                ],
                [
                    'id' => 'dupe-2',
                    'summary' => 'GOTENGAGED',
                    'start' => ['dateTime' => '2027-02-14T18:00:00-05:00'],
                    'end' => ['dateTime' => '2027-02-14T21:00:00-05:00'],
                ],
            ],
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $first = Event::where('google_event_id', 'dupe-1')->firstOrFail();
    $second = Event::where('google_event_id', 'dupe-2')->firstOrFail();

    expect($first->event_tag)->toBe('NVHHHGOTENGAGED')
        ->and($second->event_tag)->toBe('NVHHHGOTENGAG-20270214');
});

test('reads the whole calendar once, then only what changed', function () {
    Http::fake([
        'www.googleapis.com/*syncToken=TOKEN-1*' => Http::response([
            'items' => [[
                'id' => 'delta-1',
                'summary' => 'NVHHH 2100',
                'start' => ['dateTime' => '2027-07-04T18:00:00-04:00'],
                'end' => ['dateTime' => '2027-07-04T21:00:00-04:00'],
            ]],
            'nextSyncToken' => 'TOKEN-2',
        ]),
        'www.googleapis.com/*' => Http::response([
            'items' => [[
                'id' => 'full-1',
                'summary' => 'NVHHH 2000',
                'start' => ['dateTime' => '2020-07-04T18:00:00-04:00'],
                'end' => ['dateTime' => '2020-07-04T21:00:00-04:00'],
            ]],
            'nextSyncToken' => 'TOKEN-1',
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();
    expect(Cache::get('google-calendar-sync-token'))->toBe('TOKEN-1');

    Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'timeMin')
        && ! str_contains($request->url(), 'syncToken'));

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Cache::get('google-calendar-sync-token'))->toBe('TOKEN-2')
        ->and(Event::where('google_event_id', 'delta-1')->exists())->toBeTrue()
        ->and(Event::where('google_event_id', 'full-1')->count())->toBe(1);
});

test('an unchanged calendar item keeps the local edit, kennel and tracks', function () {
    $item = [
        'id' => 'etag-1',
        'summary' => 'NVHHH 1400',
        'etag' => '"aaa"',
        'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
        'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
    ];

    Http::fake(['www.googleapis.com/*' => Http::sequence()
        ->push(['items' => [$item], 'nextSyncToken' => 'TOKEN-1'])
        ->push(['items' => [$item], 'nextSyncToken' => 'TOKEN-2'])]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $event = Event::where('google_event_id', 'etag-1')->firstOrFail();
    $event->update(['name' => 'Typo fixed by hand']);

    EventTrack::create([
        'event_id' => $event->id,
        'user_id' => $this->admin->id,
        'name' => 'trail.gpx',
        'path' => "gpx/{$event->id}/trail.gpx",
        'size' => 10,
        'points' => [[40.0, -77.0]],
    ]);

    // Force the full-read path: no token, so the whole calendar is replayed.
    Cache::forget('google-calendar-sync-token');

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $event->refresh();

    expect($event->name)->toBe('Typo fixed by hand')
        ->and($event->event_tag)->toBe('NVHHH1400')
        ->and($event->kennel?->abbreviation)->toBe('NVHHH')
        ->and($event->tracks()->count())->toBe(1);
});

test('a changed calendar item still wins over a local edit', function () {
    $before = [
        'id' => 'etag-2',
        'summary' => 'NVHHH 1400',
        'etag' => '"aaa"',
        'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
        'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
    ];

    $after = [
        'id' => 'etag-2',
        'summary' => 'NVHHH 1400 moved',
        'etag' => '"bbb"',
        'start' => ['dateTime' => '2026-11-08T18:00:00-05:00'],
        'end' => ['dateTime' => '2026-11-08T21:00:00-05:00'],
    ];

    Http::fake(['www.googleapis.com/*' => Http::sequence()
        ->push(['items' => [$before], 'nextSyncToken' => 'TOKEN-1'])
        ->push(['items' => [$after], 'nextSyncToken' => 'TOKEN-2'])]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    $event = Event::where('google_event_id', 'etag-2')->firstOrFail();
    $event->update(['name' => 'Hand edit']);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect($event->refresh()->name)->toBe('NVHHH 1400 moved')
        ->and($event->starts_at->toDateString())->toBe('2026-11-08');
});

test('an expired sync token falls back to a full read', function () {
    Cache::forever('google-calendar-sync-token', 'STALE');

    Http::fakeSequence()
        ->push([], 410)
        ->push([
            'items' => [[
                'id' => 'fresh-1',
                'summary' => 'NVHHH 2000',
                'start' => ['dateTime' => '2020-07-04T18:00:00-04:00'],
                'end' => ['dateTime' => '2020-07-04T21:00:00-04:00'],
            ]],
            'nextSyncToken' => 'FRESH',
        ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    expect(Event::where('google_event_id', 'fresh-1')->exists())->toBeTrue()
        ->and(Cache::get('google-calendar-sync-token'))->toBe('FRESH');
});

test('logs the same counts it prints', function () {
    Log::spy();

    Http::fake([
        'www.googleapis.com/*' => Http::response([
            'items' => [
                [
                    'id' => 'log-1',
                    'summary' => 'NVHHH 1300',
                    'start' => ['dateTime' => '2026-11-07T18:00:00-05:00'],
                    'end' => ['dateTime' => '2026-11-07T21:00:00-05:00'],
                ],
                [
                    'id' => 'log-cancelled',
                    'summary' => 'Cancelled Hash',
                    'status' => 'cancelled',
                    'start' => ['dateTime' => '2020-01-01T18:00:00-05:00'],
                    'end' => ['dateTime' => '2020-01-01T20:00:00-05:00'],
                ],
            ],
            'nextSyncToken' => 'TOKEN-1',
        ]),
    ]);

    $this->artisan('app:sync-google-calendar')->assertSuccessful();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context = []): bool => $message === 'google calendar sync: imported events'
            && $context === ['created' => 1, 'updated' => 0, 'cancelled' => 1]
    )->once();
});

test('logs a missing default owner', function () {
    Log::spy();
    User::query()->delete();

    $this->artisan('app:sync-google-calendar')->assertFailed();

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message): bool => $message === 'google calendar sync: no user found to own imported events'
    )->once();
});
