<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $orders = \App\Models\Order::whereNull('verified_at')->get();
    foreach ($orders as $order) {
        $order->verify();

        // remove stale orders that will never be verified
        $order->refresh();
        if ($order->created_at->diffInHours() > 1.0) {
            $order->delete();
        }
    }
})->everyFiveMinutes();