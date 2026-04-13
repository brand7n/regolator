<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Authenticate::redirectUsing(function ($request) {
            return '/canihazemail';
        });

        Event::listen(MessageSent::class, function (MessageSent $event) {
            $to = implode(', ', array_map(fn ($addr) => $addr->getAddress(), $event->message->getTo()));
            $subject = $event->message->getSubject();
            Log::info("mail sent: {$subject} to {$to}");
        });
    }
}
