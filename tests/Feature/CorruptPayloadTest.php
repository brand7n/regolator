<?php

use App\Livewire\Auth\QuickLoginForm;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

test('a tampered livewire payload gets a bare 419 instead of a 500', function () {
    $snapshot = json_encode([
        'data' => [],
        'memo' => [
            'id' => 'abc',
            'name' => 'auth.quick-login-form',
            'path' => 'canihazemail',
            'release' => ReleaseToken::generate(QuickLoginForm::class),
        ],
        'checksum' => 'not-the-real-checksum',
    ]);

    $this->from('/canihazemail')
        ->postJson('/livewire/update', [
            'components' => [['snapshot' => $snapshot, 'updates' => (object) [], 'calls' => []]],
        ])
        // Livewire's own render() answers production traffic with an empty 419,
        // which its JS turns into "This page has expired. Refresh?".
        ->assertStatus(419);
});

it('does not report the corrupt snapshot as an error', function () {
    expect(app(ExceptionHandler::class)->shouldReport(new CorruptComponentPayloadException))->toBeFalse();
});
