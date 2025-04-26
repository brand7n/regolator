<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();

Schedule::call(function () {
    $orders = \App\Models\Order::whereNull('verified_at')
        ->where('status', \App\Models\OrderStatus::PaypalPending->value)
        ->get();
    foreach ($orders as $order) {
        $order->verify();

        // update stale orders that will never be verified
        // TODO: is this necessary?
        $order->refresh();
        if ($order->modified_at->diffInHours() > 1.0 
            && $order->status == \App\Models\OrderStatus::PaypalPending->value) {
            $order->status = \App\Models\OrderStatus::Accepted->value;
            $order->save();
        }
    }
})->everyFiveMinutes();