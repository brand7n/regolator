<?php

use App\Filament\Widgets\ActivityLogWidget;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(2),
        'base_price' => 5000,
        'event_tag' => 'ACTIVITY_TEST',
        'private' => false,
        'created_by' => $this->admin->id,
    ]);
});

test('user update logs changed field names in description', function () {
    $this->user->kennel = 'Pittsburgh H3';
    $this->user->save();

    $activity = $this->user->activities()->where('description', 'like', '%updated%')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('user updated')
        ->and($activity->description)->toContain('Test User');
});

test('user update with logOnlyDirty skips unchanged saves', function () {
    $count = $this->user->activities()->count();

    $this->user->save();

    expect($this->user->activities()->count())->toBe($count);
});

test('order update logs user and event in description', function () {
    $order = Order::create([
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::Invited,
    ]);

    $order->status = OrderStatus::PaypalPending;
    $order->save();

    $activity = $order->activities()->where('description', 'like', '%updated%')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->description)->toContain('Test User')
        ->and($activity->description)->toContain('Test Event')
        ->and($activity->description)->toContain('PAYPAL_PENDING');
});

test('activity log format shows changed fields for user updates', function () {
    $this->user->kennel = 'Eerie';
    $this->user->nerd_name = 'Pat';
    $this->user->save();

    $activity = $this->user->activities()->where('description', 'like', '%updated%')->latest('id')->first();
    $changed = $activity->properties->get('attributes', []);
    $old = $activity->properties->get('old', []);
    $ignore = ['updated_at', 'remember_token', 'email_verified_at', 'password'];

    $fields = collect($changed)
        ->filter(fn ($value, $key) => ! in_array($key, $ignore) && ($value !== ($old[$key] ?? null)))
        ->keys()
        ->all();

    expect($fields)->toContain('kennel')
        ->and($fields)->toContain('nerd_name')
        ->and($fields)->not->toContain('updated_at');
});

test('login-only changes do not create activity log entries', function () {
    $count = $this->user->activities()->count();

    $this->user->email_verified_at = now();
    $this->user->remember_token = 'new-token';
    $this->user->save();

    expect($this->user->activities()->count())->toBe($count);
});

test('activity log widget renders subject and changed fields', function () {
    $this->user->kennel = 'Changed Kennel';
    $this->user->nerd_name = 'Changed Nerd';
    $this->user->save();

    Livewire::actingAs($this->admin)
        ->test(ActivityLogWidget::class)
        ->assertSee('Subject')
        ->assertSee('Test User')
        ->assertSee('kennel')
        ->assertSee('nerd_name');
});

test('activity log widget filters out generic updated entries', function () {
    // Simulate an old-style "updated" entry
    activity()
        ->performedOn($this->user)
        ->causedBy($this->admin)
        ->log('updated');

    $this->user->kennel = 'New Kennel';
    $this->user->save();

    Livewire::actingAs($this->admin)
        ->test(ActivityLogWidget::class)
        ->assertSee('user updated')
        ->assertDontSee('"updated"');
});
