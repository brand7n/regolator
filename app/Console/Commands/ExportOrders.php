<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class ExportOrders extends Command
{
    protected $signature = 'app:export-orders {eventId}
        {--paid-only : Only include orders with verified payments}
        {--pdf : Generate a PDF check-in sheet instead of CSV (paid orders only)}
        {--comment : Include the order comment column}
        {--order-id : Include the PayPal order ID column}
        {--short-bus : Include the short bus column}';

    protected $description = 'Export user/order data for a specific event ID';

    public function handle(): int
    {
        $eventId = $this->argument('eventId');
        $event = Event::findOrFail($eventId);
        $fieldDefinitions = data_get($event->properties, 'fields', []);
        $fieldNames = array_column($fieldDefinitions, 'name');

        $query = Order::query()
            ->select('orders.*', 'users.name as user_name', 'users.email', 'users.shirt_size', 'users.kennel', 'users.nerd_name', 'users.phone', 'users.short_bus')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.event_id', $eventId);

        if ($this->option('paid-only') || $this->option('pdf')) {
            $query->where('orders.status', OrderStatus::PaymentVerified);
        }

        if ($this->option('pdf')) {
            $orders = $query->orderByRaw('LOWER(users.name)')->get();

            return $this->exportSignupSheet($event, $orders);
        }

        $orders = $query->get();

        Storage::makeDirectory('exports');
        $filename = "exports/orders_{$eventId}.csv";
        $handle = fopen(Storage::path($filename), 'w');

        $optionalColumns = $this->optionalColumns();
        fputcsv($handle, array_merge(
            ['user_name', 'email', 'shirt_size', 'kennel', 'nerd_name', 'phone', 'status'],
            array_column($optionalColumns, 'header'),
            $fieldNames
        ));

        foreach ($orders as $order) {
            $info = $order->event_info ?? [];
            $row = [$order->user_name ?? null, $order->email ?? null, $order->shirt_size ?? null, $order->kennel ?? null, $order->nerd_name ?? null, $order->phone ?? null, $order->status?->value];
            foreach ($optionalColumns as $column) {
                $row[] = $column['value']($order);
            }
            foreach ($fieldNames as $field) {
                $row[] = $info[$field] ?? null;
            }
            fputcsv($handle, $row);
        }

        fclose($handle);
        $this->info("Export complete: storage/app/$filename");

        return Command::SUCCESS;
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    protected function exportSignupSheet(Event $event, Collection $orders): int
    {
        Storage::makeDirectory('exports');
        $filename = "exports/signup_sheet_{$event->id}.pdf";

        $pdf = Pdf::loadView('exports.signup-sheet', [
            'event' => $event,
            'orders' => $orders,
            'showComment' => (bool) $this->option('comment'),
            'showOrderId' => (bool) $this->option('order-id'),
            'showShortBus' => (bool) $this->option('short-bus'),
        ])->setPaper('letter', 'landscape');

        Storage::put($filename, $pdf->output());

        $this->info("PDF export complete: storage/app/$filename");

        return Command::SUCCESS;
    }

    /**
     * @return list<array{header: string, value: \Closure(Order): string|null}>
     */
    protected function optionalColumns(): array
    {
        $columns = [];

        if ($this->option('comment')) {
            $columns[] = [
                'header' => 'comment',
                'value' => fn (Order $order): ?string => $order->comment,
            ];
        }

        if ($this->option('order-id')) {
            $columns[] = [
                'header' => 'order_id',
                'value' => fn (Order $order): ?string => $order->order_id,
            ];
        }

        if ($this->option('short-bus')) {
            $columns[] = [
                'header' => 'short_bus',
                'value' => fn (Order $order): string => in_array($order->short_bus, ['Y', '1', 1, true], true) ? 'Yes' : 'No',
            ];
        }

        return $columns;
    }
}
