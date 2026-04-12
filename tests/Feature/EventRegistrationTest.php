<?php

use App\Livewire\Paypal;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->publicEvent = Event::create([
        'name' => 'Public Event',
        'kennel' => 'Test Kennel',
        'description' => 'A public event',
        'location' => '123 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'PUBLIC_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);

    $this->privateEvent = Event::create([
        'name' => 'Private Event',
        'kennel' => 'Test Kennel',
        'description' => 'A private event',
        'location' => '456 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 10000,
        'event_tag' => 'PRIVATE_TEST',
        'private' => true,
        'created_by' => $this->user->id,
    ]);

    $this->freeEvent = Event::create([
        'name' => 'Free Event',
        'kennel' => 'Test Kennel',
        'description' => 'A free event',
        'location' => '789 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 0,
        'event_tag' => 'FREE_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('public event shows waiver without existing order', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->publicEvent->id])
        ->assertSee('Participating in hashing')
        ->assertSee('I Accept')
        ->assertDontSee('Join Waitlist');
});

test('private event shows waitlist without existing order', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->privateEvent->id])
        ->assertSee('Join Waitlist')
        ->assertDontSee('I Accept');
});

test('public event creates order on accept terms', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->publicEvent->id])
        ->call('accept_terms');

    $order = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->publicEvent->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Accepted);
});

test('private event does not create order on accept terms', function () {
    // Create an invited order first so waiver shows
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->privateEvent->id,
        'status' => OrderStatus::Invited,
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->privateEvent->id])
        ->call('accept_terms');

    $orders = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->privateEvent->id)
        ->get();

    // Should still only have the one invited order, not a second one
    expect($orders)->toHaveCount(1);
});

test('free public event goes directly to payment verified', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->freeEvent->id])
        ->call('accept_terms');

    $order = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->freeEvent->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();
});

test('free private event with invite goes directly to payment verified', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->freeEvent->id,
        'status' => OrderStatus::Invited,
    ]);

    // Make the free event private for this test
    $this->freeEvent->update(['private' => true]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->freeEvent->id])
        ->call('accept_terms');

    $order = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->freeEvent->id)
        ->first();

    expect($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();
});

test('paid public event shows price after accepting terms', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->publicEvent->id])
        ->call('accept_terms')
        ->assertSee('TOTAL: $50.00');
});

test('private event waitlist creates order with waitlisted status', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->privateEvent->id])
        ->call('waitlist');

    $order = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->privateEvent->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Waitlisted);
});
