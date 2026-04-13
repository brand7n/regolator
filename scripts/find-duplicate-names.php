<?php

// Run with: php artisan tinker scripts/find-duplicate-names.php

use App\Models\Order;
use App\Models\User;

$duplicates = User::selectRaw('LOWER(name) as lname, COUNT(*) as c')
    ->groupByRaw('LOWER(name)')
    ->having('c', '>', 1)
    ->pluck('lname');

echo "Found {$duplicates->count()} duplicate name groups:\n\n";

foreach ($duplicates as $name) {
    $users = User::whereRaw('LOWER(name) = ?', [$name])->get();

    echo str_repeat('─', 60)."\n";
    echo "Name: {$users->first()->name}\n";

    foreach ($users as $user) {
        $verified = $user->email_verified_at ? 'verified' : 'unverified';
        echo "  User #{$user->id}: {$user->email} ({$verified})\n";

        $orders = Order::where('user_id', $user->id)->get();
        if ($orders->isEmpty()) {
            echo "    No orders\n";
        } else {
            foreach ($orders as $order) {
                echo "    Order #{$order->id}: event={$order->event_id} status={$order->status->value}\n";
            }
        }
    }
    echo "\n";
}
