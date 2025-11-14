<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $orders = \App\Models\Order::whereNull('verified_at')
        ->where('status', \App\Models\OrderStatus::PaypalPending->value)
        ->get();
    foreach ($orders as $order) {
        if ($order->updated_at->gt(now()->subHour())) {
            Log::info('Verifying order: ' . $order->order_id);
            $order->verify();
        } else {
            Log::warning('No longer attempting to verify order: ' . $order->order_id);
            $order->status = \App\Models\OrderStatus::Accepted;
            $order->save();
        }
    }
})->everyFiveMinutes();
