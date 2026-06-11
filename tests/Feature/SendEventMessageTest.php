<?php

use App\Jobs\SendEventMessage;
use App\Mail\EventMessage;
use App\Models\Event;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\MessageRecipientStatus;
use App\Models\MessageStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Job Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'JOB_TEST',
        'private' => false,
        'created_by' => $this->admin->id,
    ]);

    $this->order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
    ]);

    $this->message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test Message',
        'body' => 'Hello **world**',
        'status' => MessageStatus::Sending,
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    $this->recipient = MessageRecipient::create([
        'message_id' => $this->message->id,
        'user_id' => $this->user->id,
        'order_id' => $this->order->id,
        'status' => MessageRecipientStatus::Queued,
    ]);
});

test('job sends email and marks recipient as sent', function () {
    Mail::fake();

    $job = new SendEventMessage($this->message, $this->recipient);
    $job->handle();

    $this->recipient->refresh();
    expect($this->recipient->status)->toBe(MessageRecipientStatus::Sent)
        ->and($this->recipient->sent_at)->not->toBeNull();

    Mail::assertSent(EventMessage::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('job marks recipient as failed on exception', function () {
    Mail::shouldReceive('to')->andThrow(new Exception('SMTP connection refused'));

    $job = new SendEventMessage($this->message, $this->recipient);
    $job->handle();

    $this->recipient->refresh();
    expect($this->recipient->status)->toBe(MessageRecipientStatus::Failed)
        ->and($this->recipient->error)->toContain('SMTP connection refused');
});

test('job updates message delivery counts', function () {
    Mail::fake();

    $job = new SendEventMessage($this->message, $this->recipient);
    $job->handle();

    $this->message->refresh();
    expect($this->message->sent_count)->toBe(1)
        ->and($this->message->status)->toBe(MessageStatus::Sent);
});

test('job generates quicklogin url', function () {
    Mail::fake();

    $job = new SendEventMessage($this->message, $this->recipient);
    $job->handle();

    Mail::assertSent(EventMessage::class, function (EventMessage $mail) {
        return str_contains($mail->url, '/quicklogin/') && str_contains($mail->url, '?action=');
    });
});
