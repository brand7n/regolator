<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Log;

class LocationMap extends Component
{
    public float $latitude = 41.17809320205751;
    public float $longitude = -77.54957166090036;
    public array $markers = [];
    public string $location;

    public function mount(int $eventId)
    {
        $event = \App\Models\Event::findOrFail($eventId);
        // TODO: set coordinates from even object
        $this->location = nl2br(e($event->location));
        $this->addMarker($this->latitude, $this->longitude);
    }

    public function render()
    {
        return view('livewire.location-map');
    }

    public function addMarker($lat, $lng)
    {
        $this->markers[] = ['lat' => $lat, 'lng' => $lng];
        $this->location .= "<br><div style=\"display: flex; justify-content: space-between; margin-top: 4px;\"><a href=\"https://maps.apple.com/?q=${lat},${lng}\" target=\"_blank\">Apple Maps</a><a href=\"https://www.google.com/maps/search/?api=1&query=${lat},${lng}\" target=\"_blank\">Google Maps</a></div>";
                Log::info("this event location: " . $this->location);

    }
}
