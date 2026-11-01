<div>
    <div wire:ignore class="w-full h-96">
        <div x-data x-init="initMap()" class="h-96 w-full" id="map"></div>
    </div>

    @script
    <script>
        window.initMap = function () {
            const location = `{!! $location !!}`;
            // Frame the event when it has a location; the club's home turf otherwise.
            const hasMarker = @js(count($markers)) > 0;
            const map = hasMarker
                ? L.map('map').setView([{{ $latitude }}, {{ $longitude }}], 13)
                : L.map('map').setView(window.MAP_CENTER, window.MAP_ZOOM);

            window.trailBasemaps(map);

            map.on('click', function(e) {
                Livewire.dispatch('addMarker', e.latlng.lat, e.latlng.lng);
            });

            const icon = L.icon({
                iconUrl: "{{ asset('leaflet/images/marker-icon.png') }}",
                shadowUrl: "{{ asset('leaflet/images/marker-shadow.png') }}",
                iconAnchor: [11, 41],
                popupAnchor: [0, -41],
            });
            @if($markers)
                L.marker([{{ $markers[0]['lat'] }}, {{ $markers[0]['lng'] }}], { icon: icon }).addTo(map).bindPopup(location).openPopup();
            @endif
        };
    </script>
    @endscript
</div>
