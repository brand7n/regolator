<div>
    <div wire:ignore class="w-full h-96">
        <div x-data x-init="initMap()" class="h-96 w-full" id="map"></div>
    </div>

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

            const icon = L.icon({
                iconUrl: "{{ asset('leaflet/images/marker-icon.png') }}",
                shadowUrl: "{{ asset('leaflet/images/marker-shadow.png') }}",
                iconAnchor: [11, 41],
                popupAnchor: [0, -41],
            });
            L.marker([{{ $markers[0]['lat'] }}, {{ $markers[0]['lng'] }}], { icon: icon }).addTo(map).bindPopup(location).openPopup();
        };
    </script>
    @endscript
</div>
