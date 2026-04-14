<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);
});

test('responses include security headers', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Download-Options', 'noopen')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
});

test('responses include hsts header', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security');
});

test('responses do not expose server info', function () {
    $response = $this->actingAs($this->user)->get('/dashboard');

    $response->assertOk()
        ->assertHeaderMissing('X-Powered-By');
});
