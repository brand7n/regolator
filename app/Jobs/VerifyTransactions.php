<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Order;

class VerifyTransactions implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orders = Order::whereNull('verified_at')->get();
        foreach ($orders as $order) {
            $order->verify();

            // remove stale orders that will never be verified
            $order->refresh();
            if ($order->created_at->diffInHours() > 1.0) {
                $order->delete();
            }
        }
    }
}
