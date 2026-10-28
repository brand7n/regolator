<?php

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    config([
        'services.strava.client_id' => '123',
        'services.strava.client_secret' => 'secret',
        'services.strava.refresh_token' => 'refresh',
    ]);

    $this->event = Event::create([
        'name' => 'NVHHH #1317: HARE NEEDED',
        'description' => '',
        'location' => 'Somewhere, PA',
        'starts_at' => '2026-11-07 23:00:00',
        'ends_at' => '2026-11-08 03:00:00',
        'base_price' => 0,
        'private' => false,
        'created_by' => 1,
        'kennel' => 'NVHHH',
    ]);
});

function stravaFake(array $activities, ?array $stream = null): void
{
    Http::fake([
        'www.strava.com/oauth/token' => Http::response(['access_token' => 'AT']),
        'www.strava.com/api/v3/athlete/activities*' => Http::response($activities),
        'www.strava.com/api/v3/activities/*/streams*' => Http::response($stream ?? [
            'latlng' => ['data' => [[40.7, -77.8], [40.71, -77.81], [40.72, -77.82]]],
        ]),
    ]);
}

test('it matches an activity by run number and saves the track', function () {
    stravaFake([[
        'id' => 9001,
        'name' => 'NVHHH #1317 hash trail',
        'start_date' => '2026-11-07T23:30:00Z',
    ]]);

    $this->artisan('app:import-strava')->assertSuccessful();

    $track = EventTrack::where('event_id', $this->event->id)->firstOrFail();

    expect($track->user_id)->toBe($this->admin->id)
        ->and($track->name)->toBe('NVHHH #1317 hash trail')
        // the fixture stream is a straight line, so RDP keeps only the endpoints
        ->and($track->points)->toBe([[40.7, -77.8], [40.72, -77.82]])
        ->and($track->path)->toBe('gpx/'.$this->event->id.'/strava-9001.gpx');

    expect(Storage::disk('public')->exists($track->path))->toBeTrue();
});

test('dry run reports matches without writing anything', function () {
    stravaFake([[
        'id' => 9002,
        'name' => 'NVHHH #1317 hash trail',
        'start_date' => '2026-11-07T23:30:00Z',
    ]]);

    $this->artisan('app:import-strava', ['--dry-run' => true])->assertSuccessful();

    expect(EventTrack::count())->toBe(0);
});

test('activities far from the event are ignored', function () {
    stravaFake([[
        'id' => 9003,
        'name' => 'NVHHH #1317 hash trail',
        'start_date' => '2026-06-15T15:00:00Z',
    ]]);

    $this->artisan('app:import-strava')->assertSuccessful();

    expect(EventTrack::count())->toBe(0);
});

test('--delta filters out matches that start too far from the event', function () {
    stravaFake([['id' => 9006, 'name' => 'Afternoon Trail Run', 'start_date' => '2026-11-07T23:30:00Z']]);

    $this->artisan('app:import-strava', ['--delta' => 60])
        ->expectsOutputToContain('skipped: start time more than 60 min')
        ->assertSuccessful();

    $this->artisan('app:import-strava', ['--delta' => 600])->assertSuccessful();

    expect(EventTrack::where('event_id', $this->event->id)->count())->toBe(1);
});

test('activities without gps points are skipped', function () {
    stravaFake(
        [['id' => 9004, 'name' => 'NVHHH #1317', 'start_date' => '2026-11-07T23:30:00Z']],
        ['latlng' => ['data' => []]],
    );

    $this->artisan('app:import-strava')->assertSuccessful();

    expect(EventTrack::count())->toBe(0);
});

test('re-importing replaces the existing track', function () {
    $activity = ['id' => 9005, 'name' => 'NVHHH #1317', 'start_date' => '2026-11-07T23:30:00Z'];

    stravaFake([$activity]);
    $this->artisan('app:import-strava')->assertSuccessful();

    stravaFake([$activity]);
    $this->artisan('app:import-strava')->assertSuccessful();

    expect(EventTrack::where('event_id', $this->event->id)->count())->toBe(1);
});

test('--skip-existing leaves an already imported track untouched', function () {
    stravaFake([['id' => 9100, 'name' => 'Hash trail', 'start_date' => '2026-11-07T23:30:00Z']]);
    $this->artisan('app:import-strava')->assertSuccessful();

    $track = EventTrack::where('event_id', $this->event->id)->firstOrFail();
    $track->update(['name' => 'edited by hand']);

    $this->artisan('app:import-strava', ['--skip-existing' => true])
        ->expectsOutputToContain('already imported')
        ->assertSuccessful();

    expect($track->refresh()->name)->toBe('edited by hand');
});

test('without --skip-existing a re-import overwrites the track', function () {
    stravaFake([['id' => 9101, 'name' => 'Hash trail', 'start_date' => '2026-11-07T23:30:00Z']]);
    $this->artisan('app:import-strava')->assertSuccessful();

    $track = EventTrack::where('event_id', $this->event->id)->firstOrFail();
    $track->update(['name' => 'edited by hand']);

    $this->artisan('app:import-strava')->assertSuccessful();

    expect($track->refresh()->name)->toBe('Hash trail');
});

test('--skip-existing still imports a different activity for the same event', function () {
    $current = [['id' => 9102, 'name' => 'Hash trail', 'start_date' => '2026-11-07T23:30:00Z']];

    Http::fake(function ($request) use (&$current) {
        return match (true) {
            str_contains($request->url(), '/streams') => Http::response([
                'latlng' => ['data' => [[40.7, -77.8], [40.71, -77.81]]],
            ]),
            str_contains($request->url(), 'oauth/token') => Http::response(['access_token' => 'AT']),
            default => Http::response($current),
        };
    });

    $this->artisan('app:import-strava')->assertSuccessful();

    $current = [['id' => 9103, 'name' => 'Hash trail B', 'start_date' => '2026-11-07T23:40:00Z']];

    $this->artisan('app:import-strava', ['--skip-existing' => true])->assertSuccessful();

    expect(EventTrack::where('event_id', $this->event->id)->firstOrFail()->name)->toBe('Hash trail B');
});

test('it fails when the token exchange fails', function () {
    Http::fake(['www.strava.com/oauth/token' => Http::response(['message' => 'Bad'], 401)]);

    $this->artisan('app:import-strava')->assertFailed();

    expect(EventTrack::count())->toBe(0);
});
