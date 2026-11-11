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

    // Every real pinned facility (admin/facility_management.php's Add
    // Facility map picker), set inline as window.GIS_FACILITIES by this
    // page — shown here regardless of isIntegrated status, so a
    // visitor can see where every facility actually is even before it
    // has the system running. Falls back to North Super Health
    // Center's own known real coordinates only if that array is
    // somehow empty/missing (same fallback assets/js/gis.js uses).
    const FACILITIES = (window.GIS_FACILITIES && window.GIS_FACILITIES.length > 0)
        ? window.GIS_FACILITIES
        : [{ id: 'north-super-health-center', name: 'North Super Health Center', lat: 13.9572279, lng: 121.1646223 }];
    const FACILITY_LATLNG = [FACILITIES[0].lat, FACILITIES[0].lng];

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

    // Every facility's own pin — added immediately, same as the full
    // map, so they're visible even before/if the barangay fetch below
    // finishes.
    const facilityMarkers = FACILITIES.map(function (facility) {
        return L.marker([facility.lat, facility.lng], {
            icon: L.divIcon({
                className: '',
                html: '<div class="home-gis-facility-marker"><i class="bi bi-hospital-fill"></i></div>',
                iconSize: [26, 26],
                iconAnchor: [13, 13],
            }),
            zIndexOffset: 1000,
        }).addTo(map);
    });

    fetch(BASE_URL + '/api/gis-data.php')
        .then(function (res) { return res.json(); })
        .then(function (geojson) {
            // Same boundary treatment as the full map (assets/js/gis.js):
            // dark outline over a white casing, faint fill for 0 cases.
            L.geoJSON(geojson, {
                interactive: false,
                style: { color: '#fff', weight: 3.5, opacity: 0.9, fill: false },
            }).addTo(map);
            const geoLayer = L.geoJSON(geojson, {
                style: function (feature) {
                    return {
                        color: '#5C0A0A',
                        weight: 1.5,
                        opacity: 1,
                        fillColor: feature.properties.color,
                        fillOpacity: feature.properties.cases > 0 ? 0.6 : 0.08,
                    };
                },
            }).addTo(map);

            const fitBounds = geoLayer.getBounds();
            facilityMarkers.forEach(function (marker) {
                fitBounds.extend(marker.getLatLng());
            });
            map.fitBounds(fitBounds, { padding: [10, 10] });

            // Name-only label per barangay (no icon/case-count — this card
            // is too small for the full page's house-icon markers) so it's
            // at least clear which shape is which, same real centroid
            // api/gis-data.php computed for the full map. ~10 barangays
            // packed into one 280px-tall card means their centroids often
            // sit too close together for every label to fit at its exact
            // spot without overlapping its neighbors — declutterLabels()
            // below nudges only the colliding ones apart (a classic
            // force-separation label-placement pass), with a thin dashed
            // leader line back to the real shape whenever a label actually
            // had to move, so every barangay keeps a legible label without
            // losing which shape it belongs to.
            let labelLayer = null;
            function declutterLabels(items) {
                const padding = 3;
                for (let iter = 0; iter < 80; iter++) {
                    let moved = false;
                    for (let i = 0; i < items.length; i++) {
                        for (let j = i + 1; j < items.length; j++) {
                            const a = items[i];
                            const b = items[j];
                            const overlapX = Math.min(a.x + a.w / 2, b.x + b.w / 2) - Math.max(a.x - a.w / 2, b.x - b.w / 2) + padding;
                            const overlapY = Math.min(a.y + a.h / 2, b.y + b.h / 2) - Math.max(a.y - a.h / 2, b.y - b.h / 2) + padding;
                            if (overlapX <= padding || overlapY <= padding) continue;
                            moved = true;
                            if (overlapX < overlapY) {
                                const shift = overlapX / 2;
                                if (a.x <= b.x) { a.x -= shift; b.x += shift; } else { a.x += shift; b.x -= shift; }
                            } else {
                                const shift = overlapY / 2;
                                if (a.y <= b.y) { a.y -= shift; b.y += shift; } else { a.y += shift; b.y -= shift; }
                            }
                        }
                    }
                    if (!moved) break;
                }
            }
            function renderLabels() {
                if (labelLayer) map.removeLayer(labelLayer);
                labelLayer = L.layerGroup().addTo(map);

                const items = geojson.features.map(function (feature) {
                    const [lng, lat] = feature.properties.centroid;
                    const anchor = map.latLngToContainerPoint([lat, lng]);
                    const name = feature.properties.name;
                    return {
                        feature: feature, lat: lat, lng: lng,
                        x: anchor.x, y: anchor.y, origX: anchor.x, origY: anchor.y,
                        // Rough rendered size of the label pill itself —
                        // ~6.2px per character at .home-gis-label's 10px/
                        // 700-weight font, plus its own padding.
                        w: name.length * 6.2 + 10, h: 16,
                    };
                });
                declutterLabels(items);

                items.forEach(function (item) {
                    const dx = item.x - item.origX;
                    const dy = item.y - item.origY;
                    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                        L.polyline([[item.lat, item.lng], map.containerPointToLatLng([item.x, item.y])], {
                            color: '#5C0A0A', weight: 1, opacity: 0.5, dashArray: '2,3', interactive: false,
                        }).addTo(labelLayer);
                    }
                    L.marker([item.lat, item.lng], { icon: L.divIcon({ className: 'home-gis-label-icon', html: '', iconSize: [0, 0] }) })
                        .bindTooltip(item.feature.properties.name, {
                            permanent: true,
                            direction: 'center',
                            className: 'home-gis-label',
                            offset: [dx, dy],
                        })
                        .addTo(labelLayer);
                });
            }
            renderLabels();

            // The preview's height changes at home.css's breakpoints (and
            // on phone rotation) — re-measure and re-fit so the barangays
            // stay centered instead of clipped. Debounced: resize fires
            // continuously while dragging a window edge.
            let resizeTimer = null;
            function refit() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () {
                    map.invalidateSize();
                    map.fitBounds(fitBounds, { padding: [10, 10] });
                    renderLabels();
                }, 150);
            }
            window.addEventListener('resize', refit);
            // The map's height now follows the cards beside it on the Home
            // grid (assets/css/home.css), which can settle a moment after
            // load (fonts, data) — re-fit whenever its own box changes size.
            if (typeof ResizeObserver !== 'undefined') {
                new ResizeObserver(refit).observe(document.getElementById('homeGisMap'));
            }
        })
        .catch(function (err) {
            console.error('Could not load barangay GeoJSON for the home preview map:', err);
        });
});
