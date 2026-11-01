<?php

use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;

beforeEach(function () {
    $this->host = User::create([
        'name' => 'Host',
        'email' => 'embed-host@example.com',
        'password' => 'secret',
    ]);

    $this->hasher = User::create([
        'name' => 'Hasher',
        'email' => 'embed-hasher@example.com',
        'password' => 'secret',
    ]);
});

function embedEvent(string $name, string $kennel, string $startsAt = '-1 week'): Event
{
    return Event::create([
        'name' => $name,
        'kennel' => $kennel,
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => $startsAt,
        'ends_at' => (new DateTimeImmutable($startsAt))->modify('+1 day'),
        'base_price' => 0,
        'event_tag' => str($name)->slug()->upper()->limit(12, ''),
        'private' => false,
        'created_by' => test()->host->id,
    ]);
}

function embedTrack(Event $event, array $points = [[41.178093, -77.549571], [41.179, -77.551]]): EventTrack
{
    return EventTrack::create([
        'event_id' => $event->id,
        'user_id' => test()->hasher->id,
        'name' => 'trail-'.$event->id.'.gpx',
        'path' => "gpx/{$event->id}/trail.gpx",
        'size' => 1024,
        'points' => $points,
        'points_overview' => EventTrack::overview($points),
    ]);
}

test('guests can view the embeddable trail map', function () {
    $event = embedEvent('Spring Trail', 'NVHHH');
    embedTrack($event);

    $this->get('/embed/trails')
        ->assertOk()
        ->assertSee('SPRING-TRAIL')
        ->assertSee('L.polyline')
        ->assertSee('41.178093');
});

test('the embed response is framable and sets no session cookie', function () {
    embedTrack(embedEvent('Spring Trail', 'NVHHH'));

    $this->get('/embed/trails')
        ->assertOk()
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
        // OSM refuses referer-less tile requests, so this must not be `no-referrer`.
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cache-Control')
        ->assertCookieMissing(config('session.cookie'));
});

test('the embed map filters by kennel and date range', function () {
    $spring = embedEvent('Spring Trail', 'NVHHH', '2025-03-01 23:00:00');
    embedTrack($spring);

    $autumn = embedEvent('Autumn Trail', 'BFH3', '2025-09-01 23:00:00');
    embedTrack($autumn);

    $this->get('/embed/trails?kennel='.$spring->kennel_id)
        ->assertOk()
        ->assertSee('SPRING-TRAIL')
        ->assertDontSee('AUTUMN-TRAIL');

    $this->get('/embed/trails?from=2025-08-01&to=2025-09-30')
        ->assertOk()
        ->assertSee('AUTUMN-TRAIL')
        ->assertDontSee('SPRING-TRAIL');
});

test('the embed requires an existing kennel', function () {
    $this->get('/embed/trails?kennel=99999')->assertNotFound();
});

test('the embed detail endpoint only returns tracks in the requested scope', function () {
    $mine = embedEvent('Detail Trail', 'NVHHH');
    $points = [[41.0, -77.0], [41.001, -77.001], [41.002, -77.0]];
    $track = embedTrack($mine, $points);

    $other = embedTrack(embedEvent('Elsewhere', 'BFH3'), [[10.0, 10.0], [10.1, 10.1]]);

    $this->getJson('/embed/trails/detail?kennel='.$mine->kennel_id.'&ids[]='.$track->id.'&ids[]='.$other->id)
        ->assertOk()
        ->assertJson([$track->id => $points])
        ->assertJsonMissing([$other->id => [[10.0, 10.0], [10.1, 10.1]]]);
});

test('the embed detail endpoint caps how many tracks one call can pull', function () {
    $event = embedEvent('Heavy Trail', 'NVHHH');
    $points = [[41.0, -77.0], [41.001, -77.001]];

    $ids = collect(range(1, 120))->map(fn ($i) => EventTrack::create([
        'event_id' => $event->id,
        'user_id' => User::create([
            'name' => "Uploader {$i}",
            'email' => "uploader{$i}@example.com",
            'password' => 'secret',
        ])->id,
        'name' => "heavy{$i}.gpx",
        'path' => "gpx/{$event->id}/heavy{$i}.gpx",
        'size' => 1,
        'points' => $points,
    ])->id);

    $response = $this->getJson('/embed/trails/detail?ids[]='.implode('&ids[]=', $ids->all()));

    expect($response->json())->toHaveCount(100);
});
