<div>
    <div wire:ignore class="w-full h-96">
        <div x-data x-init="initMap()" class="h-96 w-full" id="map"></div>
    </div>

    @assets
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    @endassets

    @script
    <script>
        window.initMap = function () {
            console.log('map loading...');
            const map = L.map('map').setView([{{ $latitude }}, {{ $longitude }}], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            map.on('click', function(e) {
                Livewire.dispatch('addMarker', e.latlng.lat, e.latlng.lng);
            });

            @foreach ($markers as $marker)
                //console.log('{!! $location !!}');
                L.marker([{{ $marker['lat'] }}, {{ $marker['lng'] }}]).addTo(map).bindPopup('{!! $location !!}').openPopup();
            @endforeach
        };
    </script>
    @endscript
</div>
