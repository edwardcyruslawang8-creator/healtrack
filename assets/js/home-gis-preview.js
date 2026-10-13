/* =============================================================
   HOME "GIS Mapping" PREVIEW WIDGET (index.php)
   ---------------------------------------------------------------
   Renders the same live api/gis-data.php GeoJSON (real per-barangay
   case counts/colors, computed from Firestore — see
   includes/public/gis_data_firestore.php) that patient/gis.php's
   full interactive map (assets/js/gis.js) uses — this used to be a
   hand-drawn placeholder SVG with made-up shapes/positions that
   didn't correspond to anything on the real map at all. Locked down
   (dragging/zoom/scroll/etc. all off, no popups) since this is only
   ever a small at-a-glance preview leading to "View Full Map" — a
   real, fully interactive copy of the same map lives there instead.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    const mapEl = document.getElementById('homeGisMap');
    if (!mapEl || typeof L === 'undefined') return; // Leaflet not loaded, or not on this page

    const BASE_URL = window.APP_BASE_URL || '';

    // Same real point assets/js/gis.js's full map uses — anchored at
    // Lipa City Hall's own real coordinates in Barangay Marauoy, right
    // behind which the facility actually sits (confirmed firsthand — see
    // that file's own comment on this constant for the full story).
    const FACILITY_LATLNG = [13.9572279, 121.1646223];

    const map = L.map('homeGisMap', {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        touchZoom: false,
        tap: false,
    });

    map.setView(FACILITY_LATLNG, 14); // refined below once the GeoJSON loads and bounds are known

    // Same real map tiles patient/gis.php's own map uses — without this,
    // the polygons were just floating colored shapes on an empty
    // background, not recognizable as an actual map.
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    // North Super Health Center's own pin — added immediately, same as
    // the full map, so it's visible even before/if the barangay fetch
    // below finishes.
    L.marker(FACILITY_LATLNG, {
        icon: L.divIcon({
            className: '',
            html: '<div class="home-gis-facility-marker"><i class="bi bi-hospital-fill"></i></div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
        }),
        zIndexOffset: 1000,
    }).addTo(map);

    fetch(BASE_URL + '/api/gis-data.php')
        .then(function (res) { return res.json(); })
        .then(function (geojson) {
            const geoLayer = L.geoJSON(geojson, {
                style: function (feature) {
                    return {
                        color: feature.properties.color,
                        weight: 1.5,
                        opacity: 0.9,
                        fillColor: feature.properties.color,
                        fillOpacity: 0.55,
                    };
                },
            }).addTo(map);

            map.fitBounds(geoLayer.getBounds().extend(FACILITY_LATLNG), { padding: [10, 10] });

            // Name-only label per barangay (no icon/case-count — this card
            // is too small for the full page's house-icon markers) so
            // it's at least clear which shape is which, same real
            // centroid api/gis-data.php computed for the full map.
            geojson.features.forEach(function (feature) {
                const [lng, lat] = feature.properties.centroid;
                L.marker([lat, lng], { icon: L.divIcon({ className: 'home-gis-label-icon', html: '', iconSize: [0, 0] }) })
                    .bindTooltip(feature.properties.name, {
                        permanent: true,
                        direction: 'center',
                        className: 'home-gis-label',
                    })
                    .addTo(map);
            });
        })
        .catch(function (err) {
            console.error('Could not load barangay GeoJSON for the home preview map:', err);
        });
});
