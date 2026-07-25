<?php

use App\Mail\PaymentConfirmation;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
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
        'name' => 'Order Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'ORDER_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('order belongs to user', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    expect($order->user->id)->toBe($this->user->id)
        ->and($order->user->name)->toBe('Test User');
});

test('order belongs to event', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    expect($order->event->id)->toBe($this->event->id)
        ->and($order->event->name)->toBe('Order Test Event');
});

test('user has many orders', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    expect($this->user->orders)->toHaveCount(1)
        ->and($this->user->orders->first()->event_id)->toBe($this->event->id);
});

test('order status is cast to enum', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PaypalPending)
        ->and($order->status->value)->toBe('PAYPAL_PENDING');
});

test('order event_info is cast to array', function () {
    $info = ['cabin' => 'A1', 'dietary' => 'vegetarian'];
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
        'event_info' => $info,
    ]);

    $order->refresh();
    expect($order->event_info)->toBe($info);
});

test('order verified_at is cast to datetime', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => '2026-01-15 12:00:00',
    ]);

    $order->refresh();
    expect($order->verified_at)->toBeInstanceOf(Carbon::class);
});

test('handle_payment_success sets status and sends confirmation', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);

    // Call the protected method via reflection
    $method = new ReflectionMethod($order, 'handle_payment_success');
    $method->invoke($order);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();

    Mail::assertQueued(PaymentConfirmation::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('activity log records order status changes', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $order->status = OrderStatus::Accepted;
    $order->save();

    $activity = $order->activities()->latest()->first();
    expect($activity->description)->toContain('Test User')
        ->and($activity->description)->toContain('Order Test Event');
});

test('activity log uses logOnlyDirty', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $initialCount = $order->activities()->count();

    // Save without changes
    $order->save();

    // Should not log a new activity when nothing changed
    expect($order->activities()->count())->toBe($initialCount);
});

test('verify returns true and processes payment on completed paypal order', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => Http::response([
            'status' => 'COMPLETED',
            'id' => 'PAYPAL123',
            'purchase_units' => [['amount' => ['value' => '50.00']]],
        ]),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL123';
    $order->save();

    $result = $order->verify();

    $order->refresh();
    expect($result)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();

    Mail::assertQueued(PaymentConfirmation::class);
});

test('verify returns false on non-completed paypal order', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => Http::response(['status' => 'CREATED', 'id' => 'PAYPAL123']),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL123';
    $order->save();

    $result = $order->verify();

    $order->refresh();
    expect($result)->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::PaypalPending);

    Mail::assertNothingSent();
});

test('verify returns false on paypal api error', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => Http::response(['error' => 'NOT_FOUND'], 404),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'INVALID';
    $order->save();

    $result = $order->verify();

    expect($result)->toBeFalse();
    Mail::assertNothingSent();
});

test('verify rejects payment amount below base price', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => Http::response([
            'status' => 'COMPLETED',
            'id' => 'PAYPAL123',
            'purchase_units' => [['amount' => ['value' => '1.00']]],
        ]),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL123';
    $order->save();

    $result = $order->verify();

    $order->refresh();
    expect($result)->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::PaypalPending)
        ->and($order->verified_at)->toBeNull();

    Mail::assertNothingSent();
});

test('verify returns false on network exception', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/*' => function () {
            throw new Exception('Connection timeout');
        },
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL123';
    $order->save();

    $result = $order->verify();

    expect($result)->toBeFalse();
    Mail::assertNothingSent();
});

test('verify captures server-side when paypal order status is approved', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/PAYPAL123' => Http::response([
            'status' => 'APPROVED',
            'id' => 'PAYPAL123',
        ]),
        '*/v2/checkout/orders/PAYPAL123/capture' => Http::response([
            'status' => 'COMPLETED',
            'id' => 'PAYPAL123',
            'purchase_units' => [
                ['payments' => ['captures' => [['amount' => ['value' => '50.00']]]]],
            ],
        ]),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL123';
    $order->save();

    $result = $order->verify();

    $order->refresh();
    expect($result)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PaymentVerified)
        ->and($order->verified_at)->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sandbox.paypal.com/v2/checkout/orders/PAYPAL123/capture';
    });

    Mail::assertQueued(PaymentConfirmation::class);
});

test('verify blocks server-side capture when event is full', function () {
    $event = Event::create([
        'name' => 'Small Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'SMALL_TEST',
        'private' => false,
        'created_by' => $this->user->id,
        'max_regos' => 1,
    ]);

    // Fill the event
    $firstUser = User::create([
        'name' => 'First User',
        'email' => 'first@example.com',
        'password' => 'secret',
    ]);
    $firstOrder = Order::create([
        'user_id' => $firstUser->id,
        'event_id' => $event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);

    $secondUser = User::create([
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'secret',
    ]);
    $secondOrder = Order::create([
        'user_id' => $secondUser->id,
        'event_id' => $event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $secondOrder->order_id = 'PAYPAL456';
    $secondOrder->save();

    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/PAYPAL456' => Http::response([
            'status' => 'APPROVED',
            'id' => 'PAYPAL456',
        ]),
    ]);

    $result = $secondOrder->verify();

    // Should return false before capturing
    expect($result)->toBeFalse();

    // Should NOT have sent capture request
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'capture');
    });
});

test('verify returns false when server-side capture fails', function () {
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'fake-token']),
        '*/v2/checkout/orders/PAYPAL789' => Http::response([
            'status' => 'APPROVED',
            'id' => 'PAYPAL789',
        ]),
        '*/v2/checkout/orders/PAYPAL789/capture' => Http::response([
            'status' => 'DECLINED',
        ], 422),
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaypalPending,
    ]);
    $order->order_id = 'PAYPAL789';
    $order->save();

    $result = $order->verify();

    $order->refresh();
    expect($result)->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::PaypalPending)
        ->and($order->verified_at)->toBeNull();

    Mail::assertNothingSent();
});
