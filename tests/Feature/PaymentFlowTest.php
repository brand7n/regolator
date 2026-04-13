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

    $this->otherUser = User::create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Paid Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 10000,
        'event_tag' => 'PAID_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('already verified order shows paid message', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->assertSee('Rego paid for');
});

test('already verified order does not show waiver or pay button', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->assertDontSee('I Accept')
        ->assertDontSee('TOTAL:');
});

test('setOrderID only updates current users order', function () {
    $myOrder = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $otherOrder = Order::create([
        'user_id' => $this->otherUser->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('setOrderID', 'PAYPAL123');

    $myOrder->refresh();
    $otherOrder->refresh();

    expect($myOrder->order_id)->toBe('PAYPAL123')
        ->and($myOrder->status)->toBe(OrderStatus::PaypalPending)
        ->and($otherOrder->order_id)->toBeNull()
        ->and($otherOrder->status)->toBe(OrderStatus::Invited);
});

test('setOrderID does nothing without an existing order', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('setOrderID', 'PAYPAL123');

    expect(Order::where('user_id', $this->user->id)->count())->toBe(0);
});

test('setOrderID does not update already verified order', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);
    $order->order_id = 'ORIGINAL123';
    $order->save();

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('setOrderID', 'HIJACK456');

    $order->refresh();
    expect($order->order_id)->toBe('ORIGINAL123');
});

test('approve does not verify another users order', function () {
    $otherOrder = Order::create([
        'user_id' => $this->otherUser->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
        'order_id' => 'OTHER_PAYPAL_ID',
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('approve', ['id' => 'OTHER_PAYPAL_ID']);

    $otherOrder->refresh();
    expect($otherOrder->status)->toBe(OrderStatus::PaypalPending)
        ->and($otherOrder->verified_at)->toBeNull();
});

test('waitlisted user sees waitlist message not waiver', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Waitlisted,
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->assertSee('You are on the waitlist')
        ->assertDontSee('I Accept')
        ->assertDontSee('TOTAL:');
});

test('cannot create duplicate orders for same event via waitlist', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Waitlisted,
    ]);

    // Component should show waitlist message, not the join button
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->assertDontSee('Join Waitlist');
});

test('invited user sees waiver not waitlist', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->assertSee('I Accept')
        ->assertDontSee('Join Waitlist')
        ->assertDontSee('You are on the waitlist');
});

test('accepting terms on invited order shows price', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('accept_terms')
        ->assertSee('TOTAL: $100.00');
});

test('edit redirects to profile page', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('edit')
        ->assertRedirect('/user/profile');
});

test('decline redirects to hashrego', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('decline')
        ->assertRedirect('https://hashrego.com');
});
