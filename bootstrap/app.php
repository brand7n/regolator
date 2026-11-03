<?php

use Bepsvpt\SecureHeaders\SecureHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
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
        // Scanners POST junk snapshots to /livewire/update, and a real user only
        // hits this with a page left open across a deploy. Reload instead of
        // showing the "corrupt data" modal, and keep it out of error monitoring.
        $exceptions->render(fn (CorruptComponentPayloadException $exception) => redirect()->back());
        $exceptions->dontReport(CorruptComponentPayloadException::class);
    })->create();
