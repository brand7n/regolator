<?php

use Remodulate\WebauthnFFI;
use Illuminate\Support\Facades\Log;

test('WebauthnFFI class can be loaded', function () {
    expect(class_exists(WebauthnFFI::class))->toBeTrue();
});

test('WebauthnFFI can be instantiated', function () {
    $ffi = new WebauthnFFI(Log::getLogger(), 'example.com', 'https://example.com');
    expect($ffi)->toBeInstanceOf(WebauthnFFI::class);
});

test('WebauthnFFI registerBegin returns valid JSON', function () {
    $ffi = new WebauthnFFI(Log::getLogger(), 'example.com', 'https://example.com');
    $result = $ffi->registerBegin([
        'user_id' => 'testuser',
        'user_name' => 'Test User'
    ]);
    expect($result)->toBeArray()
        ->and(json_encode($result))->toBeJson();
});

test('WebauthnFFI registerFinish returns valid JSON', function () {
    $ffi = new WebauthnFFI(Log::getLogger(), 'example.com', 'https://example.com');
    $result = $ffi->registerFinish([
        'rp_id' => 'example.com',
        'rp_origin' => 'https://example.com',
        'client_data' => base64_encode(json_encode([
            'type' => 'webauthn.create',
            'challenge' => base64_encode(random_bytes(32)),
            'origin' => 'https://example.com'
        ])),
        'attestation_object' => base64_encode(random_bytes(100))
    ]);
    expect($result)->toBeArray()
        ->and(json_encode($result))->toBeJson();
});

test('WebauthnFFI loginBegin returns valid JSON', function () {
    $ffi = new WebauthnFFI(Log::getLogger(), 'example.com', 'https://example.com');
    $result = $ffi->authenticateBegin([
        'user_id' => 'testuser123'
    ]);
    expect($result)->toBeArray()
        ->and(json_encode($result))->toBeJson();
});

test('WebauthnFFI loginFinish returns valid JSON', function () {
    $ffi = new WebauthnFFI(Log::getLogger(), 'example.com', 'https://example.com');
    $result = $ffi->authenticateFinish([
        'auth_state' => json_encode([
            'challenge' => base64_encode(random_bytes(32)),
            'rp_id' => 'example.com',
            'allow_credentials' => []
        ]),
        'client_data' => base64_encode(json_encode([
            'type' => 'webauthn.get',
            'challenge' => base64_encode(random_bytes(32)),
            'origin' => 'https://example.com'
        ]))
    ]);
    expect($result)->toBeArray()
        ->and(json_encode($result))->toBeJson();
}); 