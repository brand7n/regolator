<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->makeEvent = fn (Carbon $startsAt, Carbon $endsAt) => Event::create([
        'name' => 'Test Event',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'base_price' => 5000,
        'event_tag' => 'SECTION_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('the waiver section disappears once the event has ended', function () {
    $event = ($this->makeEvent)(now()->subDays(2), now()->subDay());

    $this->actingAs($this->user)
        ->get("/events/{$event->event_tag}")
        ->assertOk()
        ->assertDontSee('ACCEPT WAIVER/PAY UP')
        ->assertSee('OH LAWD THEY COMIN');
});

test('the waiver section is still offered until the event ends', function () {
    $event = ($this->makeEvent)(now()->subHour(), now()->addHour());

    $this->actingAs($this->user)
        ->get("/events/{$event->event_tag}")
        ->assertOk()
        ->assertSee('ACCEPT WAIVER/PAY UP');
});

test('tracks stay hidden until the event starts', function () {
    $event = ($this->makeEvent)(now()->addDay(), now()->addDays(2));

    $this->actingAs($this->user)
        ->get("/events/{$event->event_tag}")
        ->assertOk()
        ->assertDontSee('TRACKS');
});

test('tracks appear once the event has started and after it ends', function () {
    $running = ($this->makeEvent)(now()->subHour(), now()->addHour());

    $this->actingAs($this->user)
        ->get("/events/{$running->event_tag}")
        ->assertOk()
        ->assertSee('TRACKS');

    $past = Event::create([
        'name' => 'Past Event',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(2),
        'base_price' => 5000,
        'event_tag' => 'SECTION_PAST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->get("/events/{$past->event_tag}")
        ->assertOk()
        ->assertSee('TRACKS');
});

test('an admin still sees the tracks section before the event starts', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    $event = ($this->makeEvent)(now()->addDay(), now()->addDays(2));

    $this->actingAs($admin)
        ->get("/events/{$event->event_tag}")
        ->assertOk()
        ->assertSee('TRACKS');
});

test('the location map only appears when the event has real coordinates', function () {
    $bare = ($this->makeEvent)(now()->subHour(), now()->addHour());

    $this->actingAs($this->user)
        ->get("/events/{$bare->event_tag}")
        ->assertOk()
        ->assertDontSee('id="map"', false);

    $placed = ($this->makeEvent)(now()->subHour(), now()->addHour());
    $placed->update(['lat' => 41.178093, 'lon' => -77.549571]);

    $this->actingAs($this->user)
        ->get("/events/{$placed->event_tag}")
        ->assertOk()
        ->assertSee('id="map"', false);
});

test('coordinates are valid only when numeric, in range and not null island', function () {
    $event = ($this->makeEvent)(now()->subHour(), now()->addHour());

    expect($event->hasCoordinates())->toBeFalse();

    $event->lat = 41.178093;
    $event->lon = -77.549571;
    expect($event->hasCoordinates())->toBeTrue();

    $event->lon = 181;
    expect($event->hasCoordinates())->toBeFalse();

    $event->lat = 91;
    $event->lon = 10;
    expect($event->hasCoordinates())->toBeFalse();

    $event->lat = 0;
    $event->lon = 0;
    expect($event->hasCoordinates())->toBeFalse();
});
