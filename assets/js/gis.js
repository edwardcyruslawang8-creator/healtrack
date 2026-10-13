/* =============================================================
   GIS MAPPING PAGE JS (patient/gis.php)
   ---------------------------------------------------------------
   Loaded only by patient/gis.php, after base.js and Leaflet's + Chart.js's
   own JS (loaded from CDN — see patient/gis.php's $externalJs).
   Handles: the "Health Trend" line chart (real case counts over the
   selected window), rendering the barangay polygons + markers from the
   real, live api/gis-data.php GeoJSON, the floating legend toggle, the
   custom locate/reset control, the search field's fuzzy barangay lookup
   (zooms to + highlights the matched barangay's shape), the date-range
   dropdown (a real page reload — see includes/public/gis-data.php),
   and the Top 5 Diseases "View by" toggle (cases vs. percent-of-total,
   both already real numbers — this only swaps which one reads first).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------------
    // Health Trend line chart — real monthly case counts, set inline by
    // patient/gis.php as window.GIS_HEALTH_TREND (see
    // includes/public/gis_data_firestore.php for how it's computed).
    // Independent of the Leaflet map below, so it still renders even if
    // that map somehow fails to load.
    // ---------------------------------------------------------------
    (function renderHealthTrendChart() {
        const canvas = document.getElementById('healthTrendChart');
        const trend = window.GIS_HEALTH_TREND;
        if (!canvas || !trend || typeof Chart === 'undefined') return;

        const rootStyle = getComputedStyle(document.documentElement);
        const gridColor = rootStyle.getPropertyValue('--border-soft').trim() || '#ECEDF3';
        const tickColor = rootStyle.getPropertyValue('--text-muted').trim() || '#6B7280';
        const lineColor = trend.direction === 'up' ? '#D93025' : (trend.direction === 'down' ? '#1E8E3E' : '#6B7280');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    data: trend.values,
                    borderColor: lineColor,
                    backgroundColor: lineColor + '1A', // ~10% opacity fill
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: lineColor,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    })();

    const mapEl = document.getElementById('gisMap');
    if (!mapEl || typeof L === 'undefined') return; // Leaflet not loaded or not on this page

    const BASE_URL = window.APP_BASE_URL || '';

    // North Super Health Center's own pin — confirmed by someone who's
    // actually been there: it's in Barangay Marauoy, right behind Lipa
    // City Hall (not Inosloban — an earlier attempt to place this from
    // web directories alone got the wrong barangay). Anchored at Lipa
    // City Hall's own real coordinates (OpenStreetMap: "Lipa City Hall,
    // Arturo Tanco Drive, Marauoy, Lipa, Batangas" — a verified townhall
    // landmark), the closest real, precise reference point available for
    // "right behind the municipal hall."
    const FACILITY_LATLNG = [13.9572279, 121.1646223];
    const FACILITY_NAME = 'North Super Health Center';

    // ---------------------------------------------------------------
    // Map init
    // ---------------------------------------------------------------
    const map = L.map('gisMap', {
        zoomControl: false, // custom-positioned zoom control added below
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    // Zoom control, bottom-right (matches the reference's control placement)
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Default view — this app's real coverage area (Lipa City), refined
    // once the GeoJSON loads and we can fit its bounds including the
    // facility marker below.
    map.setView(FACILITY_LATLNG, 14);
    let initialBounds = null;
    const barangayLayers = {}; // real barangay name -> its Leaflet layer, filled in once the GeoJSON below loads

    // ---------------------------------------------------------------
    // North Super Health Center's own marker — added immediately (not
    // waiting on the barangay GeoJSON fetch below) since it doesn't
    // depend on that data at all, and should always be visible even if
    // that fetch is slow or fails.
    // ---------------------------------------------------------------
    const facilityIcon = L.divIcon({
        className: '',
        html: '<div class="facility-marker"><i class="bi bi-hospital-fill"></i></div>',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
    });
    const facilityMarker = L.marker(FACILITY_LATLNG, { icon: facilityIcon, zIndexOffset: 1000 }).addTo(map);
    facilityMarker.bindTooltip(
        '<div class="bl-name">' + FACILITY_NAME + '</div>',
        { permanent: true, direction: 'top', offset: [0, -14], className: 'facility-label-tooltip' }
    );
    facilityMarker.bindPopup('<strong>' + FACILITY_NAME + '</strong><br>Serving 11 covered barangays across Lipa City.');

    // ---------------------------------------------------------------
    // Fuzzy barangay-name matching for the search field — handles
    // partial input ("mara" -> "Marawoy") via plain substring matching,
    // and typos ("Marawoi", "Ballintawak") via Levenshtein edit
    // distance, since a real visitor searching a barangay name by hand
    // won't always get the spelling exactly right.
    // ---------------------------------------------------------------
    function hcLevenshtein(a, b) {
        const m = a.length;
        const n = b.length;
        if (m === 0) return n;
        if (n === 0) return m;
        const dp = new Array(n + 1);
        for (let j = 0; j <= n; j++) dp[j] = j;
        for (let i = 1; i <= m; i++) {
            let prev = dp[0];
            dp[0] = i;
            for (let j = 1; j <= n; j++) {
                const temp = dp[j];
                dp[j] = a[i - 1] === b[j - 1] ? prev : 1 + Math.min(prev, dp[j], dp[j - 1]);
                prev = temp;
            }
        }
        return dp[n];
    }
    function hcNormalize(s) {
        return s.toLowerCase().trim().replace(/[^a-z0-9]/g, '');
    }
    /** @return string|null The best-matching real barangay name, or null if nothing is close enough. */
    function hcFuzzyFindBarangay(query, names) {
        const q = hcNormalize(query);
        if (!q) return null;

        let best = null;
        let bestScore = -Infinity;
        names.forEach(function (name) {
            const n = hcNormalize(name);
            let score;
            if (n.indexOf(q) !== -1 || q.indexOf(n) !== -1) {
                // A real substring either way (partial typing, or a query
                // longer than the name) always wins over a fuzzy guess.
                score = 1000 - Math.abs(n.length - q.length);
            } else {
                const dist = hcLevenshtein(q, n);
                const similarity = 1 - dist / Math.max(q.length, n.length);
                if (similarity < 0.55) return; // too different to be a real typo of this name
                score = similarity * 100;
            }
            if (score > bestScore) {
                bestScore = score;
                best = name;
            }
        });
        return best;
    }

    // ---------------------------------------------------------------
    // Custom "locate / reset view" control, stacked under the zoom control
    // ---------------------------------------------------------------
    const LocateControl = L.Control.extend({
        options: { position: 'bottomright' },
        onAdd: function () {
            const btn = L.DomUtil.create('button', 'locate-control-btn');
            btn.innerHTML = '<i class="bi bi-crosshair"></i>';
            btn.type = 'button';
            btn.setAttribute('aria-label', 'Reset map view');
            L.DomEvent.disableClickPropagation(btn);
            L.DomEvent.on(btn, 'click', function () {
                if (initialBounds) {
                    map.fitBounds(initialBounds, { padding: [30, 30] });
                }
            });
            return btn;
        },
    });
    map.addControl(new LocateControl());

    // ---------------------------------------------------------------
    // Barangay polygons + markers — ?range= keeps this in sync with the
    // real window the date-range dropdown selected server-side
    // (window.GIS_RANGE, set inline by patient/gis.php).
    // ---------------------------------------------------------------
    const gisRange = window.GIS_RANGE || '3m';
    // "Cases" on its own is ambiguous (cases of what, over what period?)
    // — every place a case count is shown spells out both: real reported
    // notifiable-disease cases, over the same selected window the page's
    // own date-range dropdown/heading already shows (window.GIS_RANGE_LABEL,
    // e.g. "last 3 months" — set alongside window.GIS_RANGE).
    const gisRangeLabel = window.GIS_RANGE_LABEL || '';
    function hcCasesLabel(cases) {
        return cases + ' disease case' + (cases === 1 ? '' : 's') + (gisRangeLabel ? ' (' + gisRangeLabel + ')' : '');
    }
    /** Real per-disease breakdown behind that total ("Dengue (4), Influenza (3)"), or '' if there's no data to break down. */
    function hcDiseaseBreakdown(diseases) {
        if (!diseases || !diseases.length) return '';
        return diseases.map(function (d) { return d.name + ' (' + d.cases + ')'; }).join(', ');
    }
    fetch(BASE_URL + '/api/gis-data.php?range=' + encodeURIComponent(gisRange))
        .then(function (res) { return res.json(); })
        .then(function (geojson) {

            const geoLayer = L.geoJSON(geojson, {
                style: function (feature) {
                    return {
                        color: feature.properties.color,
                        weight: 2,
                        opacity: 0.9,
                        fillColor: feature.properties.color,
                        fillOpacity: 0.55,
                    };
                },
                onEachFeature: function (feature, layer) {
                    const p = feature.properties;
                    barangayLayers[p.name] = layer;

                    const breakdown = hcDiseaseBreakdown(p.diseases);

                    layer.bindPopup(
                        '<strong>' + p.name + '</strong><br>' +
                        hcCasesLabel(p.cases) + ' &middot; ' + p.intensity +
                        (breakdown ? '<br><span class="bht-breakdown">' + breakdown + '</span>' : '')
                    );

                    // Hovering the shape itself surfaces its real case
                    // count AND which real diseases make up that count
                    // right away, without needing a click — a normal
                    // (non-permanent) tooltip, so Leaflet shows/hides it
                    // on mouseover/mouseout automatically; sticky:true
                    // lets it follow the cursor around the shape instead
                    // of staying pinned to one spot.
                    layer.bindTooltip(
                        '<div class="bht-name">' + p.name + '</div>' +
                        '<div class="bht-cases">' + hcCasesLabel(p.cases) + ' &middot; ' + p.intensity + '</div>' +
                        (breakdown ? '<div class="bht-breakdown">' + breakdown + '</div>' : ''),
                        { sticky: true, direction: 'top', className: 'barangay-hover-tooltip' }
                    );

                    layer.on('mouseover', function () {
                        layer.setStyle({ fillOpacity: 0.75, weight: 3 });
                    });
                    layer.on('mouseout', function () {
                        layer.setStyle({ fillOpacity: 0.55, weight: 2 });
                    });
                    layer.on('click', function () {
                        layer.openPopup();
                    });
                },
            }).addTo(map);

            // Extend the fit to include the facility marker too, so it's
            // never cropped out of the initial view even if it happens
            // to sit right at the edge of the barangay shapes.
            initialBounds = geoLayer.getBounds().extend(facilityMarker.getLatLng());
            map.fitBounds(initialBounds, { padding: [30, 30] });

            // Centroid marker + permanent label for each barangay
            geojson.features.forEach(function (feature) {
                const p = feature.properties;
                const [lng, lat] = p.centroid;

                const icon = L.divIcon({
                    className: '',
                    html: '<div class="barangay-marker" style="background:' + p.color + '"><i class="bi bi-house-fill"></i></div>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 17],
                });

                const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
                // Kept short ("disease cases", no period repeated) since
                // this label is permanently on-screen for every barangay
                // at once — the full "(last 3 months)" wording lives in
                // the hover tooltip/popup instead, which only show one at
                // a time.
                marker.bindTooltip(
                    '<div class="bl-name">' + p.name + '</div><div class="bl-cases">' + p.cases + ' disease case' + (p.cases === 1 ? '' : 's') + '</div>',
                    {
                        permanent: true,
                        direction: 'bottom',
                        offset: [0, 6],
                        className: 'barangay-label-tooltip',
                    }
                );
                marker.on('click', function () {
                    const breakdown = hcDiseaseBreakdown(p.diseases);
                    L.popup()
                        .setLatLng([lat, lng])
                        .setContent(
                            '<strong>' + p.name + '</strong><br>' + hcCasesLabel(p.cases) + ' &middot; ' + p.intensity +
                            (breakdown ? '<br><span class="bht-breakdown">' + breakdown + '</span>' : '')
                        )
                        .openOn(map);
                });
            });
        })
        .catch(function (err) {
            console.error('Could not load barangay GeoJSON:', err);
        });

    // ---------------------------------------------------------------
    // Floating "Legend" button — toggles a compact in-map legend panel
    // ---------------------------------------------------------------
    const legendBtn = document.getElementById('mapLegendToggle');
    const legendPanel = document.getElementById('mapLegendPanel');
    if (legendBtn && legendPanel) {
        legendBtn.addEventListener('click', function () {
            legendPanel.classList.toggle('show');
        });
    }

    // ---------------------------------------------------------------
    // Barangay search — fuzzy-matches the typed text against the real
    // barangay names (hcFuzzyFindBarangay above), then zooms to and
    // highlights that one barangay's shape. Triggered by the search
    // field's Enter key (the Apply Filters/Reset buttons were removed
    // since neither actually filtered anything yet).
    // ---------------------------------------------------------------
    let highlightResetTimer = null;
    function hcHighlightBarangay(name) {
        const layer = barangayLayers[name];
        if (!layer) return;

        map.fitBounds(layer.getBounds(), { padding: [60, 60], maxZoom: 16 });
        layer.openPopup();

        // A stronger, longer-lived version of the existing hover style —
        // reverts on its own so it doesn't look "stuck" if the visitor
        // never touches the map again after searching.
        if (highlightResetTimer) {
            clearTimeout(highlightResetTimer);
        }
        layer.setStyle({ weight: 4, fillOpacity: 0.8 });
        layer.bringToFront();
        highlightResetTimer = setTimeout(function () {
            layer.setStyle({ weight: 2, fillOpacity: 0.55 });
        }, 3000);
    }

    const gisFilterForm = document.getElementById('gisFilterForm');
    const gisSearchInput = document.getElementById('gisSearchInput');
    const gisSearchFeedback = document.getElementById('gisSearchFeedback');
    // The red-border highlight (.no-match) is styled on .gis-search-field
    // (the bordered box), not the bare <input> inside it.
    const gisSearchFieldBox = gisSearchInput ? gisSearchInput.closest('.gis-search-field') : null;
    if (gisFilterForm) {
        gisFilterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const query = gisSearchInput ? gisSearchInput.value.trim() : '';

            if (gisSearchFeedback) gisSearchFeedback.textContent = '';
            if (gisSearchFieldBox) gisSearchFieldBox.classList.remove('no-match');

            if (query === '') {
                if (initialBounds) {
                    map.fitBounds(initialBounds, { padding: [30, 30] });
                }
                return;
            }

            if (Object.keys(barangayLayers).length === 0) {
                // Map data hasn't finished loading yet — a real "no match"
                // would be misleading here.
                if (gisSearchFeedback) gisSearchFeedback.textContent = 'Still loading the map — try again in a moment.';
                return;
            }

            const match = hcFuzzyFindBarangay(query, Object.keys(barangayLayers));
            if (match) {
                hcHighlightBarangay(match);
            } else {
                if (gisSearchFeedback) gisSearchFeedback.textContent = 'No matching barangay found for "' + query + '".';
                if (gisSearchFieldBox) gisSearchFieldBox.classList.add('no-match');
            }
        });
    }

    // ---------------------------------------------------------------
    // Date-range dropdown — a real, full page reload (form.submit()
    // bypasses the 'submit' event entirely, so it doesn't go through
    // the client-side-only barangay search handler above) to
    // ?date_range=..., which includes/public/gis-data.php resolves
    // into a real Firestore aggregation window server-side.
    // ---------------------------------------------------------------
    const gisDateRangeSelect = document.getElementById('gisDateRangeSelect');
    if (gisDateRangeSelect && gisFilterForm) {
        gisDateRangeSelect.addEventListener('change', function () {
            gisFilterForm.submit();
        });
    }

    // ---------------------------------------------------------------
    // Top 5 Diseases "View by" toggle — shows ONLY the selected real
    // number per row (case count OR percent of total, not both at
    // once). Both values are already real (stashed as data-cases/
    // data-percent on each row by patient/gis.php) — this never changes
    // the actual data, only which one is displayed, so it's a plain
    // client-side swap with no reload needed.
    // ---------------------------------------------------------------
    const topDiseasesViewBy = document.getElementById('topDiseasesViewBy');
    if (topDiseasesViewBy) {
        topDiseasesViewBy.addEventListener('change', function () {
            const byPercentage = topDiseasesViewBy.value === 'percentage';
            document.querySelectorAll('.disease-cases').forEach(function (el) {
                el.textContent = byPercentage ? el.dataset.percent : el.dataset.cases;
            });
        });
    }

});
