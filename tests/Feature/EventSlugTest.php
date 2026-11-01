<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->makeEvent = fn (string $tag, string $startsAt = '2026-02-14 10:00:00') => Event::create([
        'name' => 'Test Event',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt,
        'base_price' => 5000,
        'event_tag' => $tag,
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('the slug is the tag verbatim', function () {
    $event = ($this->makeEvent)('NVHHH1900');

    expect($event->slug)->toBe('NVHHH1900')
        ->and(route('events.show', $event))->toEndWith('/events/NVHHH1900');
});

test('the event page resolves by slug', function () {
    $event = ($this->makeEvent)('NVHHH1900');

    $this->actingAs($this->user)
        ->get('/events/NVHHH1900')
        ->assertOk()
        ->assertSee($event->name);
});

test('links mailed under the old lowercased slug still resolve', function () {
    $event = ($this->makeEvent)('GOTENGAGED');
    $event->forceFill(['slug' => 'holidaze'])->saveQuietly();

    $this->actingAs($this->user)
        ->get('/events/holidaze')
        ->assertOk()
        ->assertSee($event->name);
});

test('a colliding tag is suffixed with the start date and then a counter', function () {
    $first = ($this->makeEvent)('GOTENGAGED');
    $sameDay = ($this->makeEvent)('GOTENGAGED');
    $alsoSameDay = ($this->makeEvent)('GOTENGAGED');
    $otherDay = ($this->makeEvent)('GOTENGAGED', '2013-09-20 10:00:00');

    expect($first->event_tag)->toBe('GOTENGAGED')
        ->and($sameDay->event_tag)->toBe('GOTENGAGED-20260214')
        ->and($alsoSameDay->event_tag)->toBe('GOTENGAGED-20260214-2')
        ->and($otherDay->event_tag)->toBe('GOTENGAGED-20130920')
        ->and($sameDay->slug)->toBe($sameDay->event_tag)
        ->and($alsoSameDay->slug)->toBe($alsoSameDay->event_tag);
});

test('a suffixed tag stays inside the soft descriptor limit', function () {
    $first = ($this->makeEvent)(str_repeat('A', 22));
    $second = ($this->makeEvent)(str_repeat('A', 22));

    expect($first->event_tag)->toHaveLength(22)
        ->and($second->event_tag)->toHaveLength(22)
        ->and($second->event_tag)->not->toBe($first->event_tag);
});

test('renaming the tag re-derives the slug', function () {
    $event = ($this->makeEvent)('OLDTRACK');
    $event->update(['event_tag' => 'NEWTRACK']);

    expect($event->slug)->toBe('NEWTRACK');
});

test('an event with no tag still gets a slug from its name', function () {
    $event = ($this->makeEvent)('NVHHH1900');
    $event->forceFill(['event_tag' => null, 'name' => 'Fall Back Name'])->save();

    expect($event->slug)->toBe('fall-back-name');
});

test('a resync that moves the start date keeps the slug', function () {
    $event = ($this->makeEvent)('GOTENGAGED');
    ($this->makeEvent)('GOTENGAGED');

    $event->update(['starts_at' => '2026-03-01 10:00:00']);

    expect($event->slug)->toBe('GOTENGAGED');
});

test('tags are reduced to what paypal accepts as a soft descriptor', function () {
    $under = ($this->makeEvent)('SPRING_FLING');
    $long = ($this->makeEvent)(str_repeat('X', 30));
    $junk = ($this->makeEvent)('!!!');

    expect($under->event_tag)->toBe('SPRINGFLING')
        ->and($long->event_tag)->toBe(str_repeat('X', 22))
        ->and($junk->event_tag)->toBeNull()
        ->and($junk->slug)->toBe('test-event');
});

test('legacy id urls still resolve', function () {
    $event = ($this->makeEvent)('NVHHH1900');

    $this->actingAs($this->user)
        ->get('/events/'.$event->id)
        ->assertOk()
        ->assertSee($event->name);
});
