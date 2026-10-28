<?php

use App\Filament\Resources\EventTrackResource\Pages\ListEventTracks;
use App\Filament\Resources\KennelResource\Pages\CreateKennel;
use App\Models\Event;
use App\Models\EventTrack;
use App\Models\Kennel;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'secret',
        'is_admin' => true,
    ]);

    $this->hasher = User::create([
        'name' => 'Hasher',
        'email' => 'hasher@example.com',
        'password' => 'secret',
    ]);

    $this->kennel = Kennel::create(['name' => 'NVHHH']);

    $this->event = Event::create([
        'name' => 'Spring Trail',
        'kennel_id' => $this->kennel->id,
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subWeek()->addDay(),
        'base_price' => 0,
        'event_tag' => 'SPRING',
        'private' => false,
        'created_by' => $this->admin->id,
    ]);
});

test('admins can browse kennels', function () {
    $this->actingAs($this->admin)->get('/admin/kennels')->assertSuccessful()->assertSee('NVHHH');
});

test('admins can create a kennel', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateKennel::class)
        ->fillForm(['name' => 'BFH3'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Kennel::where('name', 'BFH3')->exists())->toBeTrue();
});

test('admins can browse tracks and filter them by kennel', function () {
    EventTrack::create([
        'event_id' => $this->event->id,
        'user_id' => $this->hasher->id,
        'name' => 'spring.gpx',
        'path' => "gpx/{$this->event->id}/spring.gpx",
        'size' => 1024,
        'points' => [[41.178093, -77.549571], [41.179, -77.551]],
    ]);

    $this->actingAs($this->admin)->get('/admin/tracks')->assertSuccessful()->assertSee('spring.gpx');

    Livewire::actingAs($this->admin)
        ->test(ListEventTracks::class)
        ->filterTable('kennel_id', $this->kennel->id)
        ->assertCanSeeTableRecords(EventTrack::all());

    $other = Kennel::create(['name' => 'BFH3']);
    Livewire::actingAs($this->admin)
        ->test(ListEventTracks::class)
        ->filterTable('kennel_id', $other->id)
        ->assertCountTableRecords(0);
});

test('deleting a track removes its file from disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put("gpx/{$this->event->id}/spring.gpx", '<gpx/>');

    $track = EventTrack::create([
        'event_id' => $this->event->id,
        'user_id' => $this->hasher->id,
        'name' => 'spring.gpx',
        'path' => "gpx/{$this->event->id}/spring.gpx",
        'size' => 5,
        'points' => [[41.178093, -77.549571], [41.179, -77.551]],
    ]);

    $track->delete();

    Storage::disk('public')->assertMissing("gpx/{$this->event->id}/spring.gpx");
});

test('non-admins cannot reach the new admin pages', function () {
    $this->actingAs($this->hasher)->get('/admin/kennels')->assertForbidden();
    $this->actingAs($this->hasher)->get('/admin/tracks')->assertForbidden();
});
