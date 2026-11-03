<?php

use App\Livewire\Auth\QuickLoginForm;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

test('a tampered livewire payload reloads instead of erroring', function () {
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
        ->assertRedirect('/canihazemail');
});

it('does not report the corrupt snapshot as an error', function () {
    expect(app(ExceptionHandler::class)->shouldReport(new CorruptComponentPayloadException))->toBeFalse();
});
