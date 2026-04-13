<?php

use App\Livewire\Auth\QuickLoginForm;
use App\Mail\QuickLogin;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

test('known email sends magic link', function () {
    User::create([
        'name' => 'Existing User',
        'email' => 'exists@example.com',
        'password' => 'secret',
    ]);

    Livewire::test(QuickLoginForm::class)
        ->set('email', 'exists@example.com')
        ->call('checkEmail');

    Mail::assertSent(QuickLogin::class, function ($mail) {
        return $mail->hasTo('exists@example.com');
    });
});

test('unknown email shows registration form', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'new@example.com')
        ->call('checkEmail')
        ->assertSet('userExists', false);

    Mail::assertNothingSent();
});

test('email is normalized to lowercase on check', function () {
    User::create([
        'name' => 'Test User',
        'email' => 'upper@example.com',
        'password' => 'secret',
    ]);

    Livewire::test(QuickLoginForm::class)
        ->set('email', 'UPPER@EXAMPLE.COM')
        ->call('checkEmail')
        ->assertSet('email', 'upper@example.com');

    Mail::assertSent(QuickLogin::class);
});

test('checkEmail validates email format', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'not-an-email')
        ->call('checkEmail')
        ->assertHasErrors(['email']);
});

test('registerAndSendLink creates user and sends email', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'newuser@example.com')
        ->set('name', 'New User')
        ->set('phone', '2125551234')
        ->call('registerAndSendLink');

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New User')
        ->and($user->phone)->not->toBeNull();

    Mail::assertSent(QuickLogin::class, function ($mail) {
        return $mail->hasTo('newuser@example.com');
    });
});

test('registerAndSendLink validates required fields', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'newuser@example.com')
        ->set('name', '')
        ->set('phone', '5551234567')
        ->call('registerAndSendLink')
        ->assertHasErrors(['name']);
});

test('registerAndSendLink rejects duplicate email', function () {
    User::create([
        'name' => 'Existing',
        'email' => 'taken@example.com',
        'password' => 'secret',
    ]);

    Livewire::test(QuickLoginForm::class)
        ->set('email', 'taken@example.com')
        ->set('name', 'New User')
        ->set('phone', '5551234567')
        ->call('registerAndSendLink')
        ->assertHasErrors(['email']);
});

test('registerAndSendLink rejects invalid phone number', function () {
    Livewire::test(QuickLoginForm::class)
        ->set('email', 'newuser@example.com')
        ->set('name', 'New User')
        ->set('phone', '123')
        ->call('registerAndSendLink')
        ->assertHasErrors(['phone']);
});
