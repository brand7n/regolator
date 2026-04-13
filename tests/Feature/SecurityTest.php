<?php

use App\Models\Event;
use App\Models\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->otherUser = User::create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('unauthenticated user cannot access dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/canihazemail');
});

test('unauthenticated user cannot access event page', function () {
    $this->get('/events/'.$this->event->id)->assertRedirect('/canihazemail');
});

test('unauthenticated user cannot access quicklogin with invalid token', function () {
    $this->get('/quicklogin/garbage')->assertStatus(403);
});

test('authenticated user can access dashboard', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertOk();
});

test('authenticated user can access event page', function () {
    $this->actingAs($this->user)->get('/events/'.$this->event->id)->assertOk();
});
