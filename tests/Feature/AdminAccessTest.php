<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    $this->regular = User::create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'password' => 'secret',
        'is_admin' => false,
    ]);
});

test('admin user can access admin panel', function () {
    $response = $this->actingAs($this->admin)->get('/admin');

    $response->assertSuccessful();
});

test('non-admin user cannot access admin panel', function () {
    $response = $this->actingAs($this->regular)->get('/admin');

    $response->assertForbidden();
});

test('canAccessPanel returns true for admin', function () {
    expect($this->admin->is_admin)->toBeTrue();
});

test('canAccessPanel returns false for non-admin', function () {
    expect($this->regular->is_admin)->toBeFalsy();
});
