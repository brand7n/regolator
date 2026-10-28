<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
        'email_verified_at' => now(),
    ]);

    $base = [
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'base_price' => 5000,
        'private' => false,
        'created_by' => $this->user->id,
    ];

    $this->upcoming = Event::create($base + [
        'name' => 'Upcoming Hash',
        'event_tag' => 'UP',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDay(),
    ]);

    $this->past = Event::create($base + [
        'name' => 'Past Hash',
        'event_tag' => 'PAST',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subDays(6),
    ]);
});

test('dashboard shows upcoming events by default', function () {
    $this->actingAs($this->user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Upcoming Hash')
        ->assertDontSee('Past Hash');
});

test('dashboard can show past events', function () {
    $this->actingAs($this->user)->get('/dashboard?show=past')
        ->assertOk()
        ->assertSee('Past Hash')
        ->assertDontSee('Upcoming Hash');
});
