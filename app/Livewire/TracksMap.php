<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventTrack;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class TracksMap extends Component
{
    public ?int $eventId = null;

    public ?int $kennelId = null;

    public ?string $from = null;

    public ?string $to = null;

    public function mount(?int $eventId = null, ?int $kennelId = null, ?string $from = null, ?string $to = null): void
    {
        $this->eventId = $eventId;
        $this->kennelId = $kennelId;
        $this->from = $from;
        $this->to = $to;

        if ($eventId !== null) {
            Event::findOrFail($eventId);
        }
    }

    public function render(): View
    {
        // Track points are view data, not component state: putting them in a public
        // property embeds megabytes into the Livewire snapshot and exhausts memory.
        return view('livewire.tracks-map', ['tracks' => EventTrack::mapPayload($this->events())]);
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
        return EventTrack::detailPayload($this->events()->pluck('id'), $ids);
    }

    /** @return Collection<int, Event> */
    private function events(): Collection
    {
        if ($this->eventId !== null) {
            return Event::query()->whereKey($this->eventId)->with('tracks.user')->get();
        }

        return Event::query()
            ->withTrails($this->kennelId, $this->from, $this->to)
            ->with('tracks.user')
            ->orderBy('starts_at')
            ->get();
    }
}
