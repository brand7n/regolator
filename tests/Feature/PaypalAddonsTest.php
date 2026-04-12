<?php

use App\Livewire\Paypal;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Addon Event',
        'kennel' => 'Test Kennel',
        'description' => 'Test',
        'location' => '123 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 10000,
        'event_tag' => 'ADDON_TEST',
        'private' => false,
        'created_by' => $this->user->id,
        'properties' => [
            'addons' => [
                ['name' => 'extra_shirt', 'label' => 'Extra Shirt', 'price' => 2500, 'tag_suffix' => '_PLUS_SHIRT'],
                ['name' => 'camping', 'label' => 'Camping Pass', 'price' => 5000, 'tag_suffix' => '_PLUS_CAMP'],
            ],
        ],
    ]);

    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);
});

test('renders addon checkboxes after accepting terms', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('accept_terms')
        ->assertSee('Extra Shirt')
        ->assertSee('$25.00')
        ->assertSee('Camping Pass')
        ->assertSee('$50.00');
});

test('toggling addon increases price', function () {
    $component = Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('accept_terms')
        ->assertSee('TOTAL: $100.00');

    $component->call('toggleAddon', 'extra_shirt')
        ->assertSee('TOTAL: $125.00');
});

test('toggling multiple addons stacks prices', function () {
    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('accept_terms')
        ->call('toggleAddon', 'extra_shirt')
        ->call('toggleAddon', 'camping')
        ->assertSee('TOTAL: $175.00');
});

test('no addons rendered when event has none defined', function () {
    $this->event->update(['properties' => []]);

    Livewire::actingAs($this->user)
        ->test(Paypal::class, ['eventId' => $this->event->id])
        ->call('accept_terms')
        ->assertDontSee('Extra Shirt')
        ->assertSee('TOTAL: $100.00');
});
