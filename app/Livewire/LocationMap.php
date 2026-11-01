<?php

namespace App\Livewire;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Event location only: one pin and its address. Trail lines live in TracksMap.
 */
class LocationMap extends Component
{
    public ?int $eventId = null;

    public float $latitude = 0.0;

    public float $longitude = 0.0;

    /** @var array<int, array{lat: float, lng: float}> */
    public array $markers = [];

    public string $location = '';

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;

        if ($eventId === null) {
            return;
        }

        $event = Event::findOrFail($eventId);
        $this->location = nl2br(e($event->location));

        // No coordinates means no pin: an [0, 0] one parks the map in the Atlantic.
        if ($event->hasCoordinates()) {
            $this->latitude = (float) $event->lat;
            $this->longitude = (float) $event->lon;
            $this->addMarker($this->latitude, $this->longitude);
        }
    }

    public function render(): View
    {
        return view('livewire.location-map');
    }

    public function addMarker(float $lat, float $lng): void
    {
        $this->markers[] = ['lat' => $lat, 'lng' => $lng];
        $this->location .= "<br><div style=\"display: flex; justify-content: space-between; margin-top: 4px;\"><a href=\"https://maps.apple.com/?q={$lat},{$lng}\" target=\"_blank\">Apple Maps</a><a href=\"https://www.google.com/maps/search/?api=1&query={$lat},{$lng}\" target=\"_blank\">Google Maps</a></div>";
    }
}
