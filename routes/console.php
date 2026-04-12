<?php

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $orders = Order::whereNull('verified_at')
        ->where('status', OrderStatus::PaypalPending->value)
        ->get();
    foreach ($orders as $order) {
        if ($order->updated_at->gt(now()->subHour())) {
            Log::info('Verifying order: '.$order->order_id);
            $order->verify();
        } else {
            Log::warning('No longer attempting to verify order: '.$order->order_id);
            $order->status = OrderStatus::Accepted;
            $order->save();
        }
    }
})->everyFiveMinutes();
