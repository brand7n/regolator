<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportOrders extends Command
{
    protected $signature = 'app:export-orders {eventId}';

    protected $description = 'Export user/order data for a specific event ID';

    public function handle(): int
    {
        $eventId = $this->argument('eventId');
        $event = Event::findOrFail($eventId);
        $fieldDefinitions = data_get($event->properties, 'fields', []);
        $fieldNames = array_column($fieldDefinitions, 'name');

        $filename = "exports/orders_{$eventId}.csv";
        Storage::makeDirectory('exports');
        $handle = fopen(Storage::path($filename), 'w');

        fputcsv($handle, array_merge(['user_name', 'status'], $fieldNames));

        $orders = Order::query()
            ->select('orders.*', 'users.name as user_name')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.event_id', $eventId)
            ->get();

        foreach ($orders as $order) {
            $info = $order->event_info ?? [];
            $row = [$order->user_name, $order->status?->value];
            foreach ($fieldNames as $field) {
                $row[] = $info[$field] ?? null;
            }
            fputcsv($handle, $row);
        }

        fclose($handle);
        $this->info("Export complete: storage/app/$filename");

        return Command::SUCCESS;
    }
}
