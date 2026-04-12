<?php

namespace App\Livewire;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LocationMap extends Component
{
    public float $latitude;

    public float $longitude;

    /** @var array<int, array{lat: float, lng: float}> */
    public array $markers = [];

    public string $location;

    public function mount(int $eventId): void
    {
        $event = Event::findOrFail($eventId);
        $this->latitude = $event->lat ?? 0.0;
        $this->longitude = $event->lon ?? 0.0;
        $this->location = nl2br(e($event->location));
        $this->addMarker($this->latitude, $this->longitude);
    }

    public function render(): View
    {
        return view('livewire.location-map');
    }

    public function addMarker(float $lat, float $lng): void
    {
        $this->markers[] = ['lat' => $lat, 'lng' => $lng];
        $this->location .= "<br><div style=\"display: flex; justify-content: space-between; margin-top: 4px;\"><a href=\"https://maps.apple.com/?q=${lat},${lng}\" target=\"_blank\">Apple Maps</a><a href=\"https://www.google.com/maps/search/?api=1&query=${lat},${lng}\" target=\"_blank\">Google Maps</a></div>";
    }
}
