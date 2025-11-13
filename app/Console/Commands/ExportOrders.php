<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;

class ExportOrders extends Command
{
    protected $signature = 'app:export-orders {eventId}';
    protected $description = 'Export user/order data for a specific event ID';

    public function handle()
    {
        $eventId = $this->argument('eventId');
        $filename = "exports/orders_{$eventId}.csv";

        Storage::makeDirectory('exports');

        $handle = fopen(Storage::path($filename), 'w');

        // CSV header
        fputcsv($handle, [
            'user_name',
            'status',
            'cabin_number',
            'reserved_by',
            'shot_stop',
        ]);

        // Query filtered by event id
        $orders = Order::query()
            ->select('orders.*', 'users.name as user_name')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.event_id', $eventId)
            ->get();

        foreach ($orders as $order) {
            $info = $order->event_info ?? [];

            fputcsv($handle, [
                $order->user_name,
                $order->status?->value,   // <-- Enum-safe CSV output
                $info['cabin_number'] ?? null,
                $info['reserved_by'] ?? null,
                $info['shot_stop'] ?? null,
            ]);
        }

        fclose($handle);

        $this->info("Export complete: storage/app/$filename");

        return Command::SUCCESS;
    }
}
