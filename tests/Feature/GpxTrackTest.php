<?php

use App\Livewire\GpxTrack;
use App\Models\Event;
use App\Models\EventTrack;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function gpxFile(string $name = 'hash.gpx'): UploadedFile
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">
  <trk><name>Hash trail</name><trkseg>
    <trkpt lat="41.178093" lon="-77.549571"><ele>200</ele></trkpt>
    <trkpt lat="41.179000" lon="-77.551000"><ele>210</ele></trkpt>
    <trkpt lat="41.180000" lon="-77.552000"><ele>220</ele></trkpt>
  </trkseg></trk>
</gpx>
XML;

    return UploadedFile::fake()->createWithContent($name, $xml);
}

beforeEach(function () {
    Storage::fake('public');

    $this->host = User::create([
        'name' => 'Host',
        'email' => 'host@example.com',
        'password' => 'secret',
    ]);

    $this->event = Event::create([
        'name' => 'Test Event',
        'kennel' => 'Test',
        'description' => 'Test',
        'location' => 'Test',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subDays(6),
        'base_price' => 5000,
        'event_tag' => 'GPX_TEST',
        'private' => false,
        'created_by' => $this->host->id,
    ]);

    $this->hasher = User::create([
        'name' => 'Hasher',
        'email' => 'hasher@example.com',
        'password' => 'secret',
    ]);

    Order::create([
        'user_id' => $this->hasher->id,
        'event_id' => $this->event->id,
        'status' => OrderStatus::PaymentVerified,
        'verified_at' => now(),
    ]);
});

test('a paid registrant can upload a gpx track', function () {
    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile())
        ->call('save')
        ->assertHasNoErrors();

    $track = EventTrack::where('event_id', $this->event->id)->sole();

    expect($track->user_id)->toBe($this->hasher->id)
        ->and($track->name)->toBe('hash.gpx')
        ->and($track->points)->toHaveCount(3)
        ->and($track->points[0])->toBe([41.178093, -77.549571])
        ->and($track->points_overview)->not->toBeNull()
        ->and($track->points_overview[0])->toBe($track->points[0])
        ->and($track->points_overview[count($track->points_overview) - 1])->toBe($track->points[2]);

    Storage::disk('public')->assertExists($track->path);
});

test('an unpaid user cannot upload', function () {
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'secret',
    ]);

    Livewire::actingAs($stranger)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile())
        ->call('save');

    expect(EventTrack::count())->toBe(0);
});

test('re-uploading replaces the previous track and file', function () {
    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile('first.gpx'))
        ->call('save');

    $firstPath = EventTrack::sole()->path;

    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile('second.gpx'))
        ->call('save');

    $track = EventTrack::sole();

    expect(EventTrack::count())->toBe(1)
        ->and($track->name)->toBe('second.gpx');

    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($track->path);
});

test('rejects files that contain no track points', function () {
    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', UploadedFile::fake()->createWithContent('empty.gpx', '<gpx version="1.1"></gpx>'))
        ->call('save')
        ->assertHasErrors('gpx');

    expect(EventTrack::count())->toBe(0);
});

test('the uploader can delete their own track', function () {
    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile())
        ->call('save');

    $track = EventTrack::sole();

    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->call('delete', $track->id);

    expect(EventTrack::count())->toBe(0);
    Storage::disk('public')->assertMissing($track->path);
});

test('non-paid users do not see the upload form', function () {
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'secret',
    ]);

    Livewire::actingAs($stranger)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->assertDontSee('Upload track');

    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->assertSee('Upload track');
});

test('the event page lists uploaded tracks and embeds them on the map', function () {
    Livewire::actingAs($this->hasher)
        ->test(GpxTrack::class, ['eventId' => $this->event->id])
        ->set('gpx', gpxFile())
        ->call('save');

    $this->actingAs($this->hasher)->get("/events/{$this->event->id}")
        ->assertOk()
        ->assertSee('hash.gpx')
        ->assertSee('41.178093');
});

function straightGpx(int $points = 500): string
{
    $xml = '<gpx version="1.1"><trk><trkseg>';

    for ($i = 0; $i < $points; $i++) {
        $xml .= sprintf('<trkpt lat="%.6f" lon="%.6f"/>', 41.0 + $i * 0.0001, -77.0 - $i * 0.0001);
    }

    return $xml.'</trkseg></trk></gpx>';
}

test('a straight line is simplified to its endpoints instead of every nth point', function () {
    $points = EventTrack::pointsFrom(straightGpx());

    expect($points)->toHaveCount(2)
        ->and($points[0])->toBe([41.0, -77.0])
        ->and($points[1])->toBe([41.0499, -77.0499]);
});

test('distance is measured in metres at the track latitude', function () {
    // 0.0001° of longitude at 41°N is ~8.4 m, not the 11.1 m a bare degree
    // delta would suggest, so an 8 m tolerance keeps this point and 10 m drops it.
    $points = [[41.0, -77.0], [41.001, -77.0001], [41.002, -77.0]];

    expect(EventTrack::simplify($points, 10.0))->toHaveCount(2)
        ->and(EventTrack::simplify($points, 8.0))->toHaveCount(3);
});

test('a dense track keeps its corners and drops the straight runs', function () {
    // Four collinear runs of 750 fixes each: the runs collapse, the corners stay.
    $corners = [[41.0, -77.0], [41.0, -76.98], [41.02, -76.98], [41.02, -77.0]];
    $points = [$corners[0]];

    for ($c = 1; $c <= 4; $c++) {
        [$fromLat, $fromLon] = $points[count($points) - 1];
        [$lat, $lon] = $corners[$c % 4];

        for ($i = 1; $i <= 750; $i++) {
            $points[] = [$fromLat + ($lat - $fromLat) * $i / 750, $fromLon + ($lon - $fromLon) * $i / 750];
        }
    }

    $simplified = EventTrack::simplify($points, 5.0);

    expect($points)->toHaveCount(3001)
        ->and($simplified)->toHaveCount(5)
        ->and($simplified[0])->toEqualWithDelta($corners[0], 1e-9)
        ->and($simplified[1])->toEqualWithDelta($corners[1], 1e-9)
        ->and($simplified[2])->toEqualWithDelta($corners[2], 1e-9)
        ->and($simplified[3])->toEqualWithDelta($corners[3], 1e-9);
});

test('a looser tolerance returns fewer points and keeps the endpoints', function () {
    $points = [];

    for ($i = 0; $i < 5000; $i++) {
        $points[] = [41.0 + $i * 0.00002, -77.0 - $i * 0.00005 + sin($i / 40) * 0.0004];
    }

    $tight = EventTrack::simplify($points, 5.0);
    $loose = EventTrack::simplify($points, 40.0);

    expect(count($tight))->toBeGreaterThan(count($loose))->toBeGreaterThan(2)
        ->and($tight[0])->toBe($points[0])
        ->and($tight[count($tight) - 1])->toBe($points[4999])
        ->and($loose[0])->toBe($points[0])
        ->and($loose[count($loose) - 1])->toBe($points[4999]);
});

test('the resample command re-derives points from the stored gpx file', function () {
    $xml = straightGpx();
    Storage::disk('public')->put('gpx/'.$this->event->id.'/old.gpx', $xml);

    $track = EventTrack::create([
        'event_id' => $this->event->id,
        'user_id' => $this->hasher->id,
        'name' => 'old.gpx',
        'path' => 'gpx/'.$this->event->id.'/old.gpx',
        'size' => strlen($xml),
        'points' => [[41.0, -77.0], [41.01, -77.01], [41.02, -77.02]],
    ]);

    $this->artisan('app:resample-tracks')->assertSuccessful();

    $track->refresh();

    expect($track->points)->toHaveCount(2)
        ->and($track->points_overview)->toBe($track->points);
});
