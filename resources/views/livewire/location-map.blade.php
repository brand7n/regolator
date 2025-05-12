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
            const location = `{!! $location !!}`;
            //console.log(location);
            const map = L.map('map').setView([{{ $latitude }}, {{ $longitude }}], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            map.on('click', function(e) {
                Livewire.dispatch('addMarker', e.latlng.lat, e.latlng.lng);
            });

            L.marker([{{ $markers[0]['lat'] }}, {{ $markers[0]['lng'] }}]).addTo(map).bindPopup(location).openPopup();
        };
    </script>
    @endscript
</div>
