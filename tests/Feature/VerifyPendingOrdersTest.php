<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
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
        'event_tag' => 'VERIFY_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('verifies recent pending orders via paypal', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => Http::response(['status' => 'COMPLETED', 'id' => 'PP123']),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PP123';
    $order->save();

    $this->artisan('app:verify-pending-orders')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();
});

test('expires stale pending orders back to accepted', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PP_OLD';
    $order->updated_at = now()->subHours(2);
    $order->save();

    // Force the updated_at to stay in the past
    Order::withoutTimestamps(fn () => Order::where('id', $order->id)->update(['updated_at' => now()->subHours(2)]));

    $this->artisan('app:verify-pending-orders')->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Accepted);
});

test('skips already verified orders', function () {
    Http::fake();

    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);

    $this->artisan('app:verify-pending-orders')->assertSuccessful();

    Http::assertNothingSent();
});

test('skips non-pending orders', function () {
    Http::fake();

    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $this->artisan('app:verify-pending-orders')->assertSuccessful();

    Http::assertNothingSent();
});
