import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// The map blades are plain <script> tags, so they expect Leaflet on the window.
window.L = L;

// PA aerial imagery (PEMA / PASDA). The service is dynamic — no tile cache, so
// each tile is a 256px server render of its bbox. One TileLayer subclass beats
// pulling in esri-leaflet for a single layer.
const PA_IMAGERY =
    'https://imagery.pasda.psu.edu/arcgis/rest/services/PEMAImagery2021_2023/MapServer/export'
    + '?bbox={bbox}&bboxSR=3857&imageSR=3857&size=256,256&dpi=96&format=jpg&transparent=false&layers=show:3&f=image';

const ArcGISExport = L.TileLayer.extend({
    getTileUrl(coords) {
        const [nw, se] = this._tileCoordsToNwSe(coords);
        const topLeft = L.CRS.EPSG3857.project(nw);
        const bottomRight = L.CRS.EPSG3857.project(se);

        return this._url.replace('{bbox}', [topLeft.x, bottomRight.y, bottomRight.x, topLeft.y].join(','));
    },
});

// Any map with nothing to draw yet lands on the club's home turf, half a
// county wide (zoom 11), instead of the middle of the Atlantic at [0, 0].
window.MAP_CENTER = L.latLng(40.793192734914996, -77.85850792321804);
window.MAP_ZOOM = 11;

// Streets by default; the imagery is one click away in the layers control.
// PEMA imagery only covers Pennsylvania, hence not the default.
window.trailBasemaps = (map) => {
    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const imagery = new ArcGISExport(PA_IMAGERY, {
        attribution: 'PEMA / PASDA',
        maxZoom: 19,
    });

    L.control
        .layers({ 'OpenStreetMap': streets, 'PA aerial imagery': imagery }, null, { collapsed: true })
        .addTo(map);

    return streets;
};
