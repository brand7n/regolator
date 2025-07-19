<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use League\Csv\Writer;
use Illuminate\Support\Facades\Response;

class csv_dump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:csv_dump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump event info to CSV';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $eventId = 1;
        $users = User::whereHas('orders', function ($query) use ($eventId) {
                $query->where('event_id', $eventId)
                      ->where('status', 'PAYMENT_VERIFIED');
            })
            ->orderBy('name')
            ->get();

        $csv = Writer::createFromString('');
        $csv->insertOne(['Name', 'Email', 'Kennel', 'Short Bus', 'Shirt Size', 'Order Created At', 'Order ID', 'Order Status', 'Comment']);

        foreach ($users as $user) {
            foreach ($user->orders as $order) {
                $csv->insertOne([
                    $user->name,
                    $user->email,
                    $user->kennel,
                    $user->short_bus,
                    $user->shirt_size,
                    $order->created_at,
                    $order->order_id,
                    $order->status->value,
                    $user->comment,
                ]);
            }
        }
        echo $csv->toString();
    }
}

