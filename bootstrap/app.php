<?php

use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\NonPublicComponentMethodCall;
use Livewire\Exceptions\PropertyNotFoundException;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Session-less, framable routes for embedding the trail map elsewhere.
        then: fn () => Route::group([], base_path('routes/embed.php')),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SecureHeadersMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Scanners POST junk snapshots to /livewire/update, and a snapshot scraped off a
        // public page is enough to replay arbitrary calls against it. None of these say
        // anything about the app working, so keep them out of error monitoring. Note this
        // also hides a mistyped wire:click in production; it is still loud in debug.
        $exceptions->dontReport([
            CorruptComponentPayloadException::class,
            MissingFileUploadsTraitException::class,
            MethodNotFoundException::class,
            NonPublicComponentMethodCall::class,
            PropertyNotFoundException::class,
            PublicPropertyNotFoundException::class,
        ]);
    })->create();
