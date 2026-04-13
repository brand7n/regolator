<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyPendingOrders extends Command
{
    protected $signature = 'app:verify-pending-orders';

    protected $description = 'Verify PayPal pending orders, expire stale ones';

    public function handle(): void
    {
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
    }
}
