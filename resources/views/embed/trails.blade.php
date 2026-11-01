<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trail maps</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-white">
    <div class="flex h-screen flex-col">
        <form method="GET" action="{{ route('embed.trails') }}" class="flex flex-wrap items-end gap-3 border-b border-gray-200 px-3 py-2 text-sm">
            <label class="text-gray-600">
                Kennel
                <select name="kennel" class="ml-1 rounded-md border-gray-300 text-sm">
                    <option value="">All</option>
                    @foreach($kennels as $option)
                        <option value="{{ $option->id }}" @selected($kennel?->is($option))>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-gray-600">
                From
                <input type="date" name="from" value="{{ $from }}" class="ml-1 rounded-md border-gray-300 text-sm">
            </label>

            <label class="text-gray-600">
                To
                <input type="date" name="to" value="{{ $to }}" class="ml-1 rounded-md border-gray-300 text-sm">
            </label>

            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500">Filter</button>

            @if($from || $to || $kennel)
                <a href="{{ route('embed.trails') }}" class="text-gray-600 underline">Reset</a>
            @endif

            <span class="ml-auto text-gray-500">
                {{ $eventCount }} {{ Str::plural('event', $eventCount) }} &middot;
                {{ $trackCount }} {{ Str::plural('track', $trackCount) }}
            </span>
        </form>

        <div id="map" class="min-h-0 flex-1"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tracks = @js($tracks);
            const map = L.map('map').setView(window.MAP_CENTER, window.MAP_ZOOM);

            window.trailBasemaps(map);

            const colors = ['#ef4444', '#3b82f6', '#22c55e', '#a855f7', '#f59e0b', '#14b8a6'];
            const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

            const drawn = tracks.map((track, i) => L.polyline(track.points, {
                color: colors[i % colors.length],
                weight: 3,
            }).addTo(map).bindPopup(
                `<strong>${esc(track.event)}</strong><br>${esc(track.date)}<br>`
                + `<a href="${esc(track.url)}" target="_blank">Download GPX</a>`
            ));

            // Coarse tier until the trail fills the screen, then the stored 5 m one.
            const DETAIL_ZOOM = 14;
            const detailUrl = @js(route('embed.trails.detail'));
            let fetching = false;

            const loadDetail = () => {
                if (fetching) return;

                const bounds = map.getBounds();
                const ids = tracks
                    .map((track, i) => (!track.detail && !track.noDetail && bounds.intersects(drawn[i].getBounds()) ? track.id : null))
                    .filter((id) => id !== null);

                if (!ids.length) return;

                fetching = true;
                const query = new URLSearchParams(@js(array_filter(['kennel' => $kennel?->id, 'from' => $from, 'to' => $to])));
                ids.forEach((id) => query.append('ids[]', id));

                fetch(`${detailUrl}?${query}`, { credentials: 'omit' })
                    .then((response) => response.ok ? response.json() : {})
                    .then((detail) => {
                        ids.forEach((id) => {
                            const track = tracks.find((t) => t.id === id);
                            const points = detail[id];

                            if (!track) return;
                            if (Array.isArray(points) && points.length > 1) track.detail = points;
                            else track.noDetail = true;
                        });
                    })
                    .catch(() => {})
                    .finally(() => {
                        fetching = false;
                        applyTier();
                    });
            };

            const applyTier = () => {
                const detailed = map.getZoom() >= DETAIL_ZOOM;
                drawn.forEach((line, i) => line.setLatLngs(detailed && tracks[i].detail ? tracks[i].detail : tracks[i].points));

                if (detailed) loadDetail();
            };

            map.on('zoomend moveend', applyTier);
            applyTier();
        });
    </script>
</body>
</html>
