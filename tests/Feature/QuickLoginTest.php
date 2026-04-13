<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);
});

test('getQuickLogin generates a token with id and hash', function () {
    $token = $this->user->getQuickLogin();
    $decrypted = json_decode(Crypt::decryptString($token), true);

    expect($decrypted)->toHaveKeys(['id', 'hash'])
        ->and($decrypted['id'])->toBe($this->user->id)
        ->and($decrypted['hash'])->toBe($this->user->password);
});

test('getQuickLogin without expiry omits expires key', function () {
    $token = $this->user->getQuickLogin();
    $decrypted = json_decode(Crypt::decryptString($token), true);

    expect($decrypted)->not->toHaveKey('expires');
});

test('getQuickLogin with expiry includes expires key', function () {
    $expiresAt = Carbon::parse('2026-12-31 23:59:59');
    $token = $this->user->getQuickLogin($expiresAt);
    $decrypted = json_decode(Crypt::decryptString($token), true);

    expect($decrypted)->toHaveKey('expires')
        ->and(Carbon::parse($decrypted['expires'])->toDateTimeString())
        ->toBe('2026-12-31 23:59:59');
});

test('quicklogin logs in a valid user', function () {
    $token = $this->user->getQuickLogin();

    $response = $this->get('/quicklogin/'.$token);

    $response->assertRedirect('dashboard');
    $this->assertAuthenticatedAs($this->user);
});

test('quicklogin redirects to action param', function () {
    $token = $this->user->getQuickLogin();

    $response = $this->get('/quicklogin/'.$token.'?action=/events/1');

    $response->assertRedirect('/events/1');
});

test('quicklogin sets email_verified_at', function () {
    expect($this->user->email_verified_at)->toBeNull();

    $token = $this->user->getQuickLogin();
    $this->get('/quicklogin/'.$token);

    $this->user->refresh();
    expect($this->user->email_verified_at)->not->toBeNull();
});

test('quicklogin rejects invalid token', function () {
    $response = $this->get('/quicklogin/invalid-token');

    $response->assertStatus(403);
    $this->assertGuest();
});

test('quicklogin rejects expired token', function () {
    $expiresAt = Carbon::now()->subHour();
    $token = $this->user->getQuickLogin($expiresAt);

    $response = $this->get('/quicklogin/'.$token);

    $response->assertStatus(403);
    $this->assertGuest();
});

test('quicklogin accepts non-expired token', function () {
    $expiresAt = Carbon::now()->addDay();
    $token = $this->user->getQuickLogin($expiresAt);

    $response = $this->get('/quicklogin/'.$token);

    $response->assertRedirect('dashboard');
    $this->assertAuthenticatedAs($this->user);
});

test('quicklogin rejects token after password change', function () {
    $token = $this->user->getQuickLogin();

    $this->user->password = 'new_password';
    $this->user->save();

    $response = $this->get('/quicklogin/'.$token);

    $response->assertStatus(403);
    $this->assertGuest();
});

test('fromQuickLogin returns user for valid token', function () {
    $token = $this->user->getQuickLogin();

    $result = User::fromQuickLogin($token);

    expect($result)->not->toBeNull()
        ->and($result['user']->id)->toBe($this->user->id)
        ->and($result['expires_at'])->toBeNull();
});

test('fromQuickLogin includes expiry when set', function () {
    $expiresAt = Carbon::parse('2026-12-31 23:59:59');
    $token = $this->user->getQuickLogin($expiresAt);

    $result = User::fromQuickLogin($token);

    expect($result)->not->toBeNull()
        ->and($result['expires_at'])->not->toBeNull()
        ->and(Carbon::parse($result['expires_at'])->toDateTimeString())->toBe('2026-12-31 23:59:59');
});

test('fromQuickLogin returns null for expired token', function () {
    $expiresAt = Carbon::now()->subHour();
    $token = $this->user->getQuickLogin($expiresAt);

    $result = User::fromQuickLogin($token);

    expect($result)->toBeNull();
});
