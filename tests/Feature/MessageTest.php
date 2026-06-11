<?php

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
    Mail::fake();

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
        'kennel' => 'Test H3',
        'nerd_name' => 'Tester',
        'shirt_size' => 'LG',
    ]);

    $this->event = Event::create([
        'name' => 'Message Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'MSG_TEST',
        'private' => false,
        'created_by' => $this->admin->id,
        'properties' => [
            'fields' => [
                ['name' => 'cabin_number', 'label' => 'Cabin #', 'type' => 'text'],
            ],
        ],
    ]);

    $this->order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'event_info' => ['cabin_number' => '7'],
    ]);
});

test('message belongs to event', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test body',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    expect($message->event->id)->toBe($this->event->id);
});

test('message belongs to creator', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test body',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    expect($message->creator->id)->toBe($this->admin->id);
});

test('message has many recipients', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test body',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    MessageRecipient::create([
        'message_id' => $message->id,
        'user_id' => $this->user->id,
        'order_id' => $this->order->id,
    ]);

    expect($message->recipients)->toHaveCount(1);
});

test('message status is cast to enum', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test body',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    $message->refresh();
    expect($message->status)->toBe(MessageStatus::Draft);
});

test('resolveRecipients filters by order status', function () {
    // Create a second user with INVITED status
    $invitedUser = User::create([
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'secret',
    ]);
    Order::create([
        'user_id' => $invitedUser->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test body',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    $recipients = $message->resolveRecipients();
    expect($recipients)->toHaveCount(1)
        ->and($recipients->first()->user_id)->toBe($this->user->id);
});

test('event message mailable renders with body and profile fields', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Important Update',
        'body' => '# Hello World',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
        'include_profile_fields' => ['name', 'kennel', 'shirt_size'],
    ]);

    $mailable = new EventMessage($message, $this->user, $this->order, 'https://example.com/login');
    $rendered = $mailable->render();

    expect($rendered)->toContain('Hello World')
        ->and($rendered)->toContain('Test User')
        ->and($rendered)->toContain('Test H3')
        ->and($rendered)->toContain('LG');
});

test('event message mailable renders with event info fields', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Cabin Info',
        'body' => 'Check your cabin',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
        'include_event_fields' => ['cabin_number'],
    ]);

    $mailable = new EventMessage($message, $this->user, $this->order, 'https://example.com/login');
    $rendered = $mailable->render();

    expect($rendered)->toContain('Cabin #')
        ->and($rendered)->toContain('7');
});

test('event message mailable includes unsubscribe header', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    $mailable = new EventMessage($message, $this->user, $this->order, 'https://example.com/login');
    $headers = $mailable->headers();

    expect($headers->text)->toHaveKey('List-Unsubscribe');
});

test('event message mailable has no attachments', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    $mailable = new EventMessage($message, $this->user, $this->order, 'https://example.com/login');
    expect($mailable->attachments())->toBe([]);
});

test('updateDeliveryCounts sets sent status when all delivered', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test',
        'status' => MessageStatus::Sending,
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    MessageRecipient::create([
        'message_id' => $message->id,
        'user_id' => $this->user->id,
        'order_id' => $this->order->id,
        'status' => MessageRecipientStatus::Sent,
    ]);

    $message->updateDeliveryCounts();
    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Sent)
        ->and($message->sent_count)->toBe(1)
        ->and($message->failed_count)->toBe(0)
        ->and($message->sent_at)->not->toBeNull();
});

test('updateDeliveryCounts sets failed status when some fail', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test',
        'status' => MessageStatus::Sending,
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    MessageRecipient::create([
        'message_id' => $message->id,
        'user_id' => $this->user->id,
        'order_id' => $this->order->id,
        'status' => MessageRecipientStatus::Failed,
        'error' => 'SMTP error',
    ]);

    $message->updateDeliveryCounts();
    $message->refresh();

    expect($message->status)->toBe(MessageStatus::Failed)
        ->and($message->sent_count)->toBe(0)
        ->and($message->failed_count)->toBe(1);
});

test('deleting message cascades to recipients', function () {
    $message = Message::create([
        'event_id' => $this->event->id,
        'created_by' => $this->admin->id,
        'subject' => 'Test',
        'body' => 'Test',
        'recipient_filter' => ['PAYMENT_VERIFIED'],
    ]);

    MessageRecipient::create([
        'message_id' => $message->id,
        'user_id' => $this->user->id,
        'order_id' => $this->order->id,
    ]);

    $message->delete();

    expect(MessageRecipient::where('message_id', $message->id)->count())->toBe(0);
});
