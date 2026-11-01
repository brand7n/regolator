<div>
    <div wire:ignore class="w-full h-96">
        <div x-data x-init="initTracksMap()" class="h-96 w-full" id="trails-map"></div>
    </div>

    @script
    <script>
        window.initTracksMap = function () {
            // No pin here: this map is only ever trail lines.
            const map = L.map('trails-map').setView(window.MAP_CENTER, window.MAP_ZOOM);

            window.trailBasemaps(map);

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

            if (lines.length) {
                map.fitBounds(L.featureGroup(lines).getBounds().pad(0.1));
            }

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
            applyTier();
        };
    </script>
    @endscript
</div>
