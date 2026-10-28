<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventTrack;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class LocationMap extends Component
{
    public ?int $eventId = null;

    public ?int $kennelId = null;

    public ?string $from = null;

    public ?string $to = null;

    public float $latitude = 0.0;

    public float $longitude = 0.0;

    /** @var array<int, array{lat: float, lng: float}> */
    public array $markers = [];

    public string $location = '';

    public function mount(?int $eventId = null, ?int $kennelId = null, ?string $from = null, ?string $to = null): void
    {
        $this->eventId = $eventId;
        $this->kennelId = $kennelId;
        $this->from = $from;
        $this->to = $to;

        if ($eventId !== null) {
            $event = Event::findOrFail($eventId);
            $event->loadMissing('tracks.user');
            $this->latitude = (float) ($event->lat ?? 0.0);
            $this->longitude = (float) ($event->lon ?? 0.0);
            $this->location = nl2br(e($event->location));
            $this->addMarker($this->latitude, $this->longitude);
        }
    }

    public function render(): View
    {
        // Track points are view data, not component state: putting them in a public
        // property embeds megabytes into the Livewire snapshot and exhausts memory.
        return view('livewire.location-map', ['tracks' => $this->assembleTracks($this->events())]);
    }

    /** @return Collection<int, Event> */
    private function events(): Collection
    {
        if ($this->eventId !== null) {
            return Event::query()->whereKey($this->eventId)->with('tracks.user')->get();
        }

        return Event::query()
            ->past()
            ->has('tracks')
            ->when($this->kennelId, fn ($query) => $query->where('kennel_id', $this->kennelId))
            ->when($this->from, fn ($query) => $query->where('starts_at', '>=', Carbon::parse($this->from)->startOfDay()))
            ->when($this->to, fn ($query) => $query->where('starts_at', '<=', Carbon::parse($this->to)->endOfDay()))
            ->with('tracks.user')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Detailed polylines for tracks already on the map, pulled in only when the
     * user zooms in far enough that the coarse tier starts showing corners.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    #[Renderless]
    public function detail(array $ids): array
    {
        return EventTrack::query()
            ->whereIn('event_id', $this->events()->pluck('id'))
            ->whereIn('id', array_slice(array_map('intval', $ids), 0, 100))
            ->get(['id', 'points'])
            ->mapWithKeys(fn (EventTrack $track) => [$track->id => $track->points ?? []])
            ->all();
    }

    public function addMarker(float $lat, float $lng): void
    {
        $this->markers[] = ['lat' => $lat, 'lng' => $lng];
        $this->location .= "<br><div style=\"display: flex; justify-content: space-between; margin-top: 4px;\"><a href=\"https://maps.apple.com/?q={$lat},{$lng}\" target=\"_blank\">Apple Maps</a><a href=\"https://www.google.com/maps/search/?api=1&query={$lat},{$lng}\" target=\"_blank\">Google Maps</a></div>";
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array<string, mixed>>
     */
    private function assembleTracks(Collection $events): array
    {
        $overview = $events->count() > 10;
        $tracks = [];

        foreach ($events as $event) {
            foreach ($event->tracks as $track) {
                $points = $track->points ?? [];

                if (count($points) < 2) {
                    continue;
                }

                $tracks[] = [
                    'id' => $track->id,
                    'event' => $event->event_tag ?: $event->name,
                    'name' => ($event->event_tag ?: $event->name).' — '.$track->user->name,
                    // ponytail: many-event maps ship the coarse tier only so a few
                    // hundred tracks fit on one page; one event ships both and the
                    // blade swaps tiers on zoom.
                    'points' => $track->points_overview ?? EventTrack::overview($points),
                    'detail' => $overview ? null : $points,
                    'date' => $event->starts_at->setTimezone('America/New_York')->format('M j, Y'),
                    'eventUrl' => route('events.show', $event),
                    'url' => Storage::url($track->path),
                ];
            }
        }

        if ($this->markers === [] && $tracks !== []) {
            [$this->latitude, $this->longitude] = $tracks[0]['points'][0];
        }

        return $tracks;
    }
}
