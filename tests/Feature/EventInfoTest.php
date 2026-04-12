<?php

use App\Livewire\EventInfo;
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
        'name' => 'Test Event',
        'kennel' => 'Test Kennel',
        'description' => 'Test',
        'location' => '123 Test St',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'TEST',
        'private' => false,
        'created_by' => $this->user->id,
        'properties' => [
            'fields' => [
                ['name' => 'cabin_number', 'label' => 'Cabin #', 'type' => 'number', 'rules' => 'required|integer|between:1,15', 'placeholder' => ''],
                ['name' => 'dietary', 'label' => 'Dietary Needs', 'type' => 'text', 'rules' => 'nullable|string|max:512', 'placeholder' => 'Any allergies?'],
            ],
        ],
    ]);
});

test('renders dynamic fields from event properties', function () {
    Livewire::actingAs($this->user)
        ->test(EventInfo::class, ['eventId' => $this->event->id])
        ->assertSee('Cabin #')
        ->assertSee('Dietary Needs');
});

test('does not render form when event has no fields', function () {
    $this->event->update(['properties' => []]);

    Livewire::actingAs($this->user)
        ->test(EventInfo::class, ['eventId' => $this->event->id])
        ->assertDontSee('Submit');
});

test('submitting saves field values to order event_info', function () {
    Livewire::actingAs($this->user)
        ->test(EventInfo::class, ['eventId' => $this->event->id])
        ->set('fields.cabin_number', 5)
        ->set('fields.dietary', 'Vegetarian')
        ->call('submit');

    $order = Order::where('user_id', $this->user->id)
        ->where('event_id', $this->event->id)
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->event_info['cabin_number'])->toBe(5)
        ->and($order->event_info['dietary'])->toBe('Vegetarian');
});

test('loads existing event_info values on mount', function () {
    Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
        'event_info' => ['cabin_number' => 7, 'dietary' => 'Vegan'],
    ]);

    Livewire::actingAs($this->user)
        ->test(EventInfo::class, ['eventId' => $this->event->id])
        ->assertSet('fields.cabin_number', 7)
        ->assertSet('fields.dietary', 'Vegan');
});

test('validates required fields', function () {
    Livewire::actingAs($this->user)
        ->test(EventInfo::class, ['eventId' => $this->event->id])
        ->set('fields.cabin_number', null)
        ->call('submit')
        ->assertHasErrors(['fields.cabin_number']);
});
