<?php

use App\Livewire\Auth\QuickLoginForm;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\NonPublicComponentMethodCall;
use Livewire\Exceptions\PropertyNotFoundException;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;
use Livewire\Features\SupportReleaseTokens\ReleaseToken;
use Livewire\Livewire;
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

it('does not report junk livewire traffic as an error', function (Closure $exception) {
    expect(app(ExceptionHandler::class)->shouldReport($exception()))->toBeFalse();
})->with([
    'corrupt snapshot' => fn () => new CorruptComponentPayloadException,
    'upload without the trait' => fn () => new MissingFileUploadsTraitException(Livewire::test(QuickLoginForm::class)->instance()),
    'bogus method' => fn () => new MethodNotFoundException('_startUpload'),
    'private method' => fn () => new NonPublicComponentMethodCall('foo'),
    'bogus nested property' => fn () => new PropertyNotFoundException('nope', 'auth.quick-login-form'),
    'bogus property' => fn () => new PublicPropertyNotFoundException('nope', 'auth.quick-login-form'),
]);
