<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
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

    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'event_info' => ['cabin_number' => 3, 'meal_pref' => 'Vegetarian'],
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
