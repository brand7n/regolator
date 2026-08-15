<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Export Event',
        'kennel' => 'Test Kennel',
        'description' => 'Test',
        'location' => '123 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'EXPORT_TEST',
        'private' => false,
        'created_by' => $this->user->id,
        'properties' => [
            'fields' => [
                ['name' => 'cabin_number', 'label' => 'Cabin #', 'type' => 'number', 'rules' => 'nullable'],
                ['name' => 'meal_pref', 'label' => 'Meal Preference', 'type' => 'text', 'rules' => 'nullable'],
            ],
        ],
    ]);

    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'comment' => 'Vegetarian, arrive Friday',
        'event_info' => ['cabin_number' => 3, 'meal_pref' => 'Vegetarian'],
    ]);
    $order->order_id = 'PAYPAL999';
    $order->save();

    $this->unpaidUser = User::create([
        'name' => 'Unpaid User',
        'email' => 'unpaid@example.com',
        'password' => 'secret',
    ]);

    Order::create([
        'user_id' => $this->unpaidUser->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Waitlisted,
    ]);
});

test('export generates CSV with dynamic field columns', function () {
    $this->artisan('app:export-orders', ['eventId' => $this->event->id])
        ->assertSuccessful();

    $filename = "exports/orders_{$this->event->id}.csv";
    expect(Storage::exists($filename))->toBeTrue();

    $content = Storage::get($filename);
    $lines = array_filter(explode("\n", $content));

    // Header row has dynamic fields
    expect($lines[0])->toContain('user_name')
        ->toContain('cabin_number')
        ->toContain('meal_pref');

    // Data row
    expect($lines[1])->toContain('Test User')
        ->toContain('3')
        ->toContain('Vegetarian');
});

test('optional columns are included when flags are passed', function () {
    $this->artisan('app:export-orders', [
        'eventId' => $this->event->id,
        '--comment' => true,
        '--order-id' => true,
        '--short-bus' => true,
    ])->assertSuccessful();

    $content = Storage::get("exports/orders_{$this->event->id}.csv");
    $lines = str_getcsv($content, "\n");
    $header = str_getcsv($lines[0]);
    $row = str_getcsv($lines[1]);

    $col = fn (string $name): string => $row[array_search($name, $header, true)];

    expect($header)->toContain('comment')
        ->toContain('order_id')
        ->toContain('short_bus')
        ->and($col('comment'))->toBe('Vegetarian, arrive Friday')
        ->and($col('order_id'))->toBe('PAYPAL999')
        ->and($col('short_bus'))->toBe('No');
});

test('optional columns are omitted by default', function () {
    $this->artisan('app:export-orders', ['eventId' => $this->event->id])
        ->assertSuccessful();

    $content = Storage::get("exports/orders_{$this->event->id}.csv");
    $header = str_getcsv(explode("\n", $content)[0]);

    expect($header)->not->toContain('comment')
        ->not->toContain('order_id')
        ->not->toContain('short_bus');
});

test('paid-only option excludes unpaid orders', function () {
    $this->artisan('app:export-orders', ['eventId' => $this->event->id, '--paid-only' => true])
        ->assertSuccessful();

    $content = Storage::get("exports/orders_{$this->event->id}.csv");

    expect($content)->toContain('Test User')
        ->not->toContain('Unpaid User');
});

test('default export includes unpaid orders', function () {
    $this->artisan('app:export-orders', ['eventId' => $this->event->id])
        ->assertSuccessful();

    $content = Storage::get("exports/orders_{$this->event->id}.csv");

    expect($content)->toContain('Unpaid User')
        ->toContain('WAITLISTED');
});

test('pdf option generates a check-in sheet with paid orders only', function () {
    $this->artisan('app:export-orders', ['eventId' => $this->event->id, '--pdf' => true])
        ->assertSuccessful();

    $filename = "exports/signup_sheet_{$this->event->id}.pdf";
    expect(Storage::exists($filename))->toBeTrue();

    $pdfBytes = Storage::get($filename);
    expect(substr($pdfBytes, 0, 5))->toBe('%PDF-');

    // Ensure no CSV was produced
    expect(Storage::exists("exports/orders_{$this->event->id}.csv"))->toBeFalse();
});

test('pdf option sorts attendees case-insensitively by name', function () {
    $bravo = User::create(['name' => 'bravo lower', 'email' => 'bravo@example.com', 'password' => 'secret']);
    $charlie = User::create(['name' => 'CHARLIE UPPER', 'email' => 'charlie@example.com', 'password' => 'secret']);

    Order::create(['user_id' => $bravo->id, 'event_id' => $this->event->id, 'status' => OrderStatus::PaymentVerified]);
    Order::create(['user_id' => $charlie->id, 'event_id' => $this->event->id, 'status' => OrderStatus::PaymentVerified]);

    $pdf = Mockery::mock(PDF::class);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(fn (string $view, array $data): bool => $view === 'exports.signup-sheet'
            && $data['orders']->pluck('user_name')->values()->all() === ['bravo lower', 'CHARLIE UPPER', 'Test User']
            && $data['showComment'] === false
            && $data['showOrderId'] === false
            && $data['showShortBus'] === false)
        ->andReturnSelf();
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('output')->andReturn('%PDF-fake');

    app()->instance('dompdf.wrapper', $pdf);

    $this->artisan('app:export-orders', ['eventId' => $this->event->id, '--pdf' => true])
        ->assertSuccessful();
});

test('pdf option passes display flags to the view', function () {
    $pdf = Mockery::mock(PDF::class);
    $pdf->shouldReceive('loadView')
        ->once()
        ->withArgs(fn (string $view, array $data): bool => $view === 'exports.signup-sheet'
            && $data['showComment'] === true
            && $data['showOrderId'] === true
            && $data['showShortBus'] === true)
        ->andReturnSelf();
    $pdf->shouldReceive('setPaper')->andReturnSelf();
    $pdf->shouldReceive('output')->andReturn('%PDF-fake');

    app()->instance('dompdf.wrapper', $pdf);

    $this->artisan('app:export-orders', [
        'eventId' => $this->event->id,
        '--pdf' => true,
        '--comment' => true,
        '--order-id' => true,
        '--short-bus' => true,
    ])->assertSuccessful();
});

test('export handles event with no custom fields', function () {
    $this->event->update(['properties' => []]);

    $this->artisan('app:export-orders', ['eventId' => $this->event->id])
        ->assertSuccessful();

    $filename = "exports/orders_{$this->event->id}.csv";
    $content = Storage::get($filename);
    $lines = array_filter(explode("\n", $content));

    // Header should only have base columns
    expect($lines[0])->toContain('user_name')
        ->toContain('status')
        ->not->toContain('cabin_number');
});
