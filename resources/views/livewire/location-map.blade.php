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
            @if($markers)
                L.marker([{{ $markers[0]['lat'] }}, {{ $markers[0]['lng'] }}], { icon: icon }).addTo(map).bindPopup(location).openPopup();
            @endif

            const tracks = @js($tracks);
            const colors = ['#ef4444', '#3b82f6', '#22c55e', '#a855f7', '#f59e0b', '#14b8a6'];
            const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            // Coarse tier until the trail fills the screen, then the stored 5 m one.
            const DETAIL_ZOOM = 14;
            const lines = tracks.map((track, i) => L.polyline(track.points, {
                color: colors[i % colors.length],
                weight: 3,
            }).addTo(map).bindPopup(
                `<a href="${esc(track.eventUrl)}" style="font-weight:600">${esc(track.event)}</a><br>`
                + `${esc(track.date)}<br>${esc(track.name)}<br>`
                + `<a href="${esc(track.url)}" target="_blank">Download GPX</a>`
            ));

            // Big maps ship only the coarse tier, so pull the detail for whatever
            // is in view once the user zooms in on it.
            let fetching = false;

            const loadDetail = () => {
                if (fetching) return;

                const bounds = map.getBounds();
                const ids = tracks
                    .map((track, i) => (!track.detail && !track.noDetail && bounds.intersects(lines[i].getBounds()) ? track.id : null))
                    .filter((id) => id !== null);

                if (!ids.length) return;

                fetching = true;
                $wire.detail(ids).then((detail) => {
                    // Only touch the ids we asked for: marking the others noDetail
                    // would permanently skip trails that were off-screen.
                    ids.forEach((id) => {
                        const track = tracks.find((t) => t.id === id);
                        const points = detail[id];

                        if (!track) return;
                        if (Array.isArray(points) && points.length > 1) track.detail = points;
                        else track.noDetail = true;
                    });
                }).finally(() => {
                    fetching = false;
                    applyTier();
                });
            };

            const applyTier = () => {
                const detailed = map.getZoom() >= DETAIL_ZOOM;
                lines.forEach((line, i) => line.setLatLngs(detailed && tracks[i].detail ? tracks[i].detail : tracks[i].points));

                if (detailed) loadDetail();
            };

            map.on('zoomend moveend', applyTier);

            if (lines.length) {
                map.fitBounds(L.featureGroup(lines).getBounds(), { padding: [24, 24] });
            }

            applyTier();
        };
    </script>
    @endscript
</div>
