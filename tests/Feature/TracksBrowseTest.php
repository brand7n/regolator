<?php

use App\Livewire\LocationMap;
use App\Models\Event;
use App\Models\EventTrack;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->host = User::create([
        'name' => 'Host',
        'email' => 'host@example.com',
        'password' => 'secret',
    ]);

    $this->user = User::create([
        'name' => 'Hasher',
        'email' => 'hasher@example.com',
        'password' => 'secret',
    ]);
});

function pastEvent(string $name, string $kennel, string $when = 'subWeek'): Event
{
    return Event::create([
        'name' => $name,
        'kennel' => $kennel,
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->{$when}(),
        'ends_at' => now()->{$when}()->addDay(),
        'base_price' => 0,
        'event_tag' => str($name)->slug()->upper()->limit(12, ''),
        'private' => false,
        'created_by' => test()->host->id,
    ]);
}

function track(Event $event, User $user, string $name = 'trail.gpx'): EventTrack
{
    return EventTrack::create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'name' => $name,
        'path' => "gpx/{$event->id}/{$name}",
        'size' => 1024,
        'points' => [[41.178093, -77.549571], [41.179, -77.551]],
    ]);
}

test('guests cannot browse trails', function () {
    $this->get('/tracks')->assertRedirect('/canihazemail');
});

test('the trails page lists kennels that have past events with tracks', function () {
    $withTrack = pastEvent('Spring Trail', 'NVHHH');
    track($withTrack, $this->user);

    pastEvent('No Track Event', 'NVHHH');

    $upcoming = Event::create([
        'name' => 'Future Trail',
        'kennel' => 'NVHHH',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addDays(8),
        'base_price' => 0,
        'private' => false,
        'created_by' => $this->host->id,
    ]);
    track($upcoming, $this->user, 'future.gpx');

    $other = pastEvent('Other Kennel Trail', 'BFH3');
    track($other, $this->user, 'other.gpx');

    $this->actingAs($this->user)->get('/tracks')
        ->assertOk()
        ->assertSee('NVHHH')
        ->assertSee('BFH3')
        ->assertDontSee('Future Trail')
        ->assertDontSee('No Track Event');
});

test('the trails page maps tracks from every kennel on one map', function () {
    $first = pastEvent('Spring Trail', 'NVHHH');
    $second = pastEvent('Other Kennel Trail', 'BFH3');

    EventTrack::create([
        'event_id' => $first->id,
        'user_id' => $this->user->id,
        'name' => 'first.gpx',
        'path' => "gpx/{$first->id}/first.gpx",
        'size' => 1024,
        'points' => [[41.178093, -77.549571], [41.179, -77.551]],
    ]);

    EventTrack::create([
        'event_id' => $second->id,
        'user_id' => $this->user->id,
        'name' => 'second.gpx',
        'path' => "gpx/{$second->id}/second.gpx",
        'size' => 1024,
        'points' => [[40.2732, -76.8867], [40.274, -76.888]],
    ]);

    $this->actingAs($this->user)->get('/tracks')
        ->assertOk()
        ->assertSee('L.polyline')
        ->assertSee('41.178093')
        ->assertSee('40.2732');
});

test('the date range filter narrows the map and the kennel list', function () {
    $spring = Event::create([
        'name' => 'Spring Trail',
        'kennel' => 'NVHHH',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => '2025-03-01 23:00:00',
        'ends_at' => '2025-03-02 03:00:00',
        'base_price' => 0,
        'private' => false,
        'created_by' => $this->host->id,
    ]);
    track($spring, $this->user, 'spring.gpx');

    $autumn = Event::create([
        'name' => 'Autumn Trail',
        'kennel' => 'NVHHH',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => '2025-09-01 23:00:00',
        'ends_at' => '2025-09-02 03:00:00',
        'base_price' => 0,
        'private' => false,
        'created_by' => $this->host->id,
    ]);
    track($autumn, $this->user, 'autumn.gpx');

    $this->actingAs($this->user)
        ->get('/tracks?from=2025-08-01&to=2025-09-30')
        ->assertOk()
        ->assertSee('Autumn Trail')
        ->assertDontSee('Spring Trail');

    $component = Livewire::actingAs($this->user)->test(LocationMap::class, [
        'from' => '2025-08-01',
        'to' => '2025-09-30',
    ]);

    expect(substr_count($component->html(), 'L.polyline'))->toBe(1)
        ->and($component->html())->toContain('autumn.gpx');
});

test('the trails page maps every track when no range is given', function () {
    $spring = pastEvent('Spring Trail', 'NVHHH');
    $autumn = pastEvent('Autumn Trail', 'BFH3', 'subWeeks');
    track($spring, $this->user, 'spring.gpx');
    track($autumn, $this->user, 'autumn.gpx');

    $component = Livewire::actingAs($this->user)->test(LocationMap::class);

    expect($component->html())->toContain('spring.gpx')->toContain('autumn.gpx')
        ->and($component->get('markers'))->toBe([]);
});

test('a kennel page shows only that kennels past events and maps their tracks', function () {
    $mine = pastEvent('Spring Trail', 'NVHHH');
    track($mine, $this->user, 'mine.gpx');

    $other = pastEvent('Other Kennel Trail', 'BFH3');
    track($other, $this->user, 'other.gpx');

    $this->actingAs($this->user)->get('/tracks?kennel='.$mine->kennel_id)
        ->assertOk()
        ->assertSee('Spring Trail')
        ->assertDontSee('Other Kennel Trail')
        ->assertSee('41.178093')
        ->assertSee('L.polyline');
});

test('the map component builds a single polyline list for a kennel', function () {
    $first = pastEvent('First Trail', 'NVHHH');
    $second = pastEvent('Second Trail', 'NVHHH', 'subWeeks');
    track($first, $this->user, 'first.gpx');
    track($second, $this->user, 'second.gpx');

    $component = Livewire::actingAs($this->user)->test(LocationMap::class, ['kennelId' => $first->kennel_id]);

    $html = $component->html();

    expect(substr_count($html, 'L.polyline'))->toBe(1)
        ->and($html)->toContain('FIRST-TRAIL')->toContain('SECOND-TRAIL');
});

test('a track popup carries the event name, date and event link', function () {
    $event = pastEvent('Popup Trail', 'NVHHH');
    track($event, $this->user, 'popup.gpx');

    $component = Livewire::actingAs($this->user)->test(LocationMap::class, ['kennelId' => $event->kennel_id]);

    $tracks = $component->viewData('tracks');

    expect($tracks)->toHaveCount(1)
        ->and($tracks[0]['event'])->toBe('POPUP-TRAIL')
        ->and($tracks[0]['date'])->toBe(now()->subWeek()->setTimezone('America/New_York')->format('M j, Y'))
        ->and($tracks[0]['eventUrl'])->toBe(route('events.show', $event))
        ->and($component->html())->toContain('bindPopup');
});

test('the map component still works for a single event', function () {
    $event = pastEvent('Solo Trail', 'NVHHH');
    track($event, $this->user);

    $component = Livewire::actingAs($this->user)->test(LocationMap::class, ['eventId' => $event->id]);

    expect($component->get('markers'))->toHaveCount(1)
        ->and($component->html())->toContain('trail.gpx')
        ->and($component->get('tracks'))->toBeNull();
});

test('a single event map ships the coarse tier plus the detailed one', function () {
    $event = pastEvent('Tier Trail', 'NVHHH');
    $points = [];

    for ($i = 0; $i < 400; $i++) {
        $points[] = [41.0 + $i * 0.00002, -77.0 - $i * 0.00005 + sin($i / 40) * 0.0004];
    }

    EventTrack::create([
        'event_id' => $event->id,
        'user_id' => $this->user->id,
        'name' => 'tier.gpx',
        'path' => 'gpx/'.$event->id.'/tier.gpx',
        'size' => 1,
        'points' => $points,
        'points_overview' => EventTrack::overview($points),
    ]);

    $tracks = Livewire::actingAs($this->user)
        ->test(LocationMap::class, ['eventId' => $event->id])
        ->viewData('tracks');

    expect($tracks[0]['detail'])->toEqual($points)
        ->and(count($tracks[0]['points']))->toBeGreaterThan(1)->toBeLessThan(count($points));
});

test('a crowded overview map ships only the coarse tier', function () {
    for ($i = 0; $i < 11; $i++) {
        track(pastEvent('Crowd '.$i, 'NVHHH'), $this->user, 'crowd'.$i.'.gpx');
    }

    $tracks = Livewire::actingAs($this->user)
        ->test(LocationMap::class)
        ->viewData('tracks');

    expect($tracks)->toHaveCount(11)
        ->and($tracks[0]['detail'])->toBeNull()
        ->and($tracks[0]['id'])->not->toBeNull()
        ->and(Livewire::actingAs($this->user)->test(LocationMap::class)->html())->toContain('$wire.detail(');
});

test('the detail action hands back the 5 m polyline for visible tracks only', function () {
    $mine = pastEvent('Detail Trail', 'NVHHH');
    $points = [[41.0, -77.0], [41.001, -77.001], [41.002, -77.0], [41.003, -77.001]];

    $track = EventTrack::create([
        'event_id' => $mine->id,
        'user_id' => $this->user->id,
        'name' => 'detail.gpx',
        'path' => 'gpx/'.$mine->id.'/detail.gpx',
        'size' => 1,
        'points' => $points,
        'points_overview' => [[41.0, -77.0], [41.003, -77.001]],
    ]);

    $other = EventTrack::create([
        'event_id' => pastEvent('Elsewhere', 'NVHHH')->id,
        'user_id' => $this->user->id,
        'name' => 'other.gpx',
        'path' => 'gpx/elsewhere.gpx',
        'size' => 1,
        'points' => [[10.0, 10.0], [10.1, 10.1]],
        'points_overview' => [[10.0, 10.0], [10.1, 10.1]],
    ]);

    $component = Livewire::actingAs($this->user)->test(LocationMap::class, ['kennelId' => $mine->kennel_id]);

    expect($component->instance()->detail([$track->id]))->toEqual([$track->id => $points])
        ->and($component->instance()->detail([$other->id]))->toEqual([$other->id => [[10.0, 10.0], [10.1, 10.1]]]);

    // A kennel filter keeps tracks from other events out of the response.
    $scoped = Livewire::actingAs($this->user)->test(LocationMap::class, ['eventId' => $mine->id]);

    expect($scoped->instance()->detail([$other->id]))->toBe([]);
});

test('track points stay out of the livewire snapshot', function () {
    $event = pastEvent('Heavy Trail', 'NVHHH');
    track($event, $this->user);

    $html = Livewire::actingAs($this->user)->test(LocationMap::class, ['kennelId' => $event->kennel_id])->html();

    preg_match('/wire:snapshot="([^"]*)"/', $html, $snapshot);

    expect($snapshot[1] ?? '')->not->toContain('41.179')
        ->and(strlen($snapshot[1] ?? ''))->toBeLessThan(1000)
        ->and($html)->toContain('41.179');
});
