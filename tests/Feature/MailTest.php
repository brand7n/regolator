<?php

use App\Livewire\Auth\QuickLoginForm;
use App\Mail\PaymentConfirmation;
use App\Mail\QuickLogin;
use App\Mail\RegoInvite;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Mail Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'MAIL_TEST',
        'private' => false,
        'created_by' => $this->user->id,
    ]);
});

test('quick login form sends magic link email', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'test@example.com')
        ->call('checkEmail');

    Mail::assertSent(QuickLogin::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

test('quick login email contains login url', function () {
    $mail = new QuickLogin($this->user, 'https://example.com/quicklogin/abc123');

    expect($mail->url)->toBe('https://example.com/quicklogin/abc123')
        ->and($mail->name)->toBe('Test User');
});

test('rego invite email uses event name in subject', function () {
    $mail = new RegoInvite($this->user, $this->event, 'https://example.com');

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Mail Test Event Rego Invite for Test User');
});

test('payment confirmation email uses event name in subject', function () {
    $mail = new PaymentConfirmation($this->user, $this->event, 'https://example.com');

    $envelope = $mail->envelope();
    expect($envelope->subject)->toBe('Mail Test Event Payment Confirmation for Test User');
});

test('rego invite includes unsubscribe header', function () {
    $mail = new RegoInvite($this->user, $this->event, 'https://example.com/login');

    $headers = $mail->headers();
    expect($headers->text)->toHaveKey('List-Unsubscribe');
});
