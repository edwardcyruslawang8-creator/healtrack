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

    // ---------------------------------------------------------------
    // Seasonal Patterns bar chart — true calendar-month totals (every
    // real year on record merged), set inline as window.GIS_SEASONAL —
    // see includes/shared/predictive_analytics_core.php's
    // hc_gis_seasonal_overview() header comment. Independent of the
    // Health Trend chart above (that one's a rolling window; this one
    // never changes with the date-range dropdown).
    // ---------------------------------------------------------------
    (function renderSeasonalChart() {
        const canvas = document.getElementById('seasonalChart');
        const seasonal = window.GIS_SEASONAL;
        if (!canvas || !seasonal || !seasonal.hasData || typeof Chart === 'undefined') return;

        const rootStyle = getComputedStyle(document.documentElement);
        const gridColor = rootStyle.getPropertyValue('--border-soft').trim() || '#ECEDF3';
        const tickColor = rootStyle.getPropertyValue('--text-muted').trim() || '#6B7280';

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: seasonal.monthlyLabels,
                datasets: [{
                    data: seasonal.topCategoryMonthly,
                    backgroundColor: '#1A73E8',
                    borderRadius: 4,
                    maxBarThickness: 22,
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

        // Real "View: [disease]" dropdown (hc_gis_seasonal_overview()'s
        // own categoryMonthly, every real category's 12-month breakdown,
        // already sent inline — this just swaps which one the SAME chart
        // renders, client-side, no extra request).
        const select = document.getElementById('seasonalDiseaseSelect');
        const caption = document.getElementById('seasonalChartCaption');
        if (select && seasonal.categoryMonthly) {
            select.addEventListener('change', function () {
                const category = select.value;
                const monthly = seasonal.categoryMonthly[category];
                if (!monthly) return;
                chart.data.datasets[0].data = monthly;
                chart.update();
                if (caption) {
                    caption.innerHTML = 'Monthly case totals for <strong>' + category.replace(/[&<>"]/g, function (ch) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
                    }) + '</strong>, every year on record.';
                }
            });
        }
    })();

    const mapEl = document.getElementById('gisMap');
    if (!mapEl || typeof L === 'undefined') return; // Leaflet not loaded or not on this page

    const BASE_URL = window.APP_BASE_URL || '';

    // Real, admin-pinned facility locations (admin/facility_management.php's
    // Add Facility map picker — see includes/firestore/facilities.php's
    // hc_get_all_facilities_summary()), set inline as window.GIS_FACILITIES
    // by this page. Falls back to North Super Health Center's own known
    // real coordinates (confirmed by someone who's actually been there:
    // it's in Barangay Marauoy, right behind Lipa City Hall — OpenStreetMap:
    // "Lipa City Hall, Arturo Tanco Drive, Marauoy, Lipa, Batangas") only
    // if that array is somehow empty/missing, so the map never regresses
    // to showing nothing.
    const FACILITIES = (window.GIS_FACILITIES && window.GIS_FACILITIES.length > 0)
        ? window.GIS_FACILITIES
        : [{ id: 'north-super-health-center', name: 'North Super Health Center', lat: 13.9572279, lng: 121.1646223 }];
    const FACILITY_LATLNG = [FACILITIES[0].lat, FACILITIES[0].lng];

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

    // Default view — this app's real coverage area (Lipa City), refined
    // once the GeoJSON loads and we can fit its bounds including the
    // facility marker below.
    map.setView(FACILITY_LATLNG, 14);

    // The map's real pixel height now comes from CSS flex-stretch
    // (assets/css/gis.css's .gis-layout/.map-card/.map-container — it
    // grows to match the left column's own height instead of a flat
    // fixed height), so Leaflet needs an explicit nudge once that
    // layout has actually settled, or it keeps using whatever size the
    // container had at the instant L.map() ran (a classic Leaflet-in-a-
    // flex-container quirk — the size callback alone isn't enough here
    // since nothing ever fires a browser resize event on page load).
    // Re-checked on real window resizes too, since the left column's
    // wrapped height (and therefore the map's matching height) can
    // change at a narrower viewport.
    window.addEventListener('load', function () { map.invalidateSize(); });
    window.addEventListener('resize', function () { map.invalidateSize(); });
    // Also catches height changes that aren't a window resize (e.g. a
    // left-column card's content changing after load).
    if (window.ResizeObserver) {
        new ResizeObserver(function () { map.invalidateSize(); }).observe(document.getElementById('gisMap'));
    }
    let initialBounds = null;
    const barangayLayers = {}; // real barangay name -> its Leaflet layer, filled in once the GeoJSON below loads

    // ---------------------------------------------------------------
    // Every real pinned facility's own marker — added immediately (not
    // waiting on the barangay GeoJSON fetch below) since none of them
    // depend on that data at all, and should always be visible even if
    // that fetch is slow or fails.
    // ---------------------------------------------------------------
    const facilityIcon = L.divIcon({
        className: '',
        html: '<div class="facility-marker"><i class="bi bi-hospital-fill"></i></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
    const facilityMarkers = FACILITIES.map(function (facility) {
        const marker = L.marker([facility.lat, facility.lng], { icon: facilityIcon, zIndexOffset: 1000 }).addTo(map);
        marker.bindTooltip(
            '<div class="bl-name">' + facility.name + '</div>',
            { permanent: true, direction: 'top', offset: [0, -12], className: 'facility-label-tooltip' }
        );
        marker.bindPopup('<strong>' + facility.name + '</strong><br>Serving 11 covered barangays across Lipa City.');
        return marker;
    });

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
    // Map controls, top-right directly under the floating Legend button
    // (assets/css/gis.css offsets that corner below it): the custom
    // "locate / reset view" button first, then zoom in/out. Leaflet
    // stacks top-corner controls in the order they're added.
    // ---------------------------------------------------------------
    const LocateControl = L.Control.extend({
        options: { position: 'topright' },
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
    L.control.zoom({ position: 'topright' }).addTo(map);

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
    /** Name, district, case count and disease breakdown — shared by the hover tooltip and both popups. */
    function hcBarangayInfoHtml(p) {
        const breakdown = hcDiseaseBreakdown(p.diseases);
        const district = p.district ? p.district + ' District' : '';
        const official = p.officialName && p.officialName !== p.name ? 'Official name: ' + p.officialName : '';
        return '<div class="bht-name">' + p.name + '</div>' +
            '<div class="bht-breakdown">' + [district, official, p.psgc ? 'PSGC ' + p.psgc : ''].filter(Boolean).join(' &middot; ') + '</div>' +
            '<div class="bht-cases">' + hcCasesLabel(p.cases) + ' &middot; ' + p.intensity + '</div>' +
            (breakdown ? '<div class="bht-breakdown">' + breakdown + '</div>' : '');
    }

    // Choropleth style — the fill color is the server's real intensity
    // tier (hc_gis_intensity_meta(), a maroon ramp that darkens as cases
    // rise); a barangay with 0 cases keeps only a faint neutral tint so
    // it reads as "nothing reported" while its outline stays visible.
    // Every outline is the same dark maroon line, drawn over a white
    // casing (boundaryCasing below), so the border between two adjacent
    // barangays stays visible whatever shades they're filled with.
    const BOUNDARY_COLOR = '#5C0A0A';
    const EMPHASIS = {
        // [extra boundary weight, extra fill opacity]
        hover: [1, 0.12],
        selected: [1.5, 0.18],
        strong: [2, 0.25],
    };
    function hcBarangayStyle(p, emphasis) {
        const baseFill = p.cases > 0 ? 0.6 : 0.08;
        const [extraWeight, extraFill] = EMPHASIS[emphasis] || [0, 0];
        return {
            color: BOUNDARY_COLOR,
            weight: 2 + extraWeight,
            opacity: 1,
            fillColor: p.color,
            fillOpacity: Math.min(baseFill + extraFill, 0.85),
        };
    }
    // The barangay whose popup is open — keeps a subtle "selected" style
    // (slightly heavier maroon outline + fill) until that popup closes.
    let selectedLayer = null;
    function hcRestyle(layer) {
        layer.setStyle(hcBarangayStyle(layer.feature.properties, layer === selectedLayer ? 'selected' : undefined));
    }

    fetch(BASE_URL + '/api/gis-data.php?range=' + encodeURIComponent(gisRange))
        .then(function (res) { return res.json(); })
        .then(function (geojson) {

            // White casing under every boundary line (non-interactive).
            L.geoJSON(geojson, {
                interactive: false,
                style: { color: '#fff', weight: 5, opacity: 0.9, fill: false },
            }).addTo(map);

            const geoLayer = L.geoJSON(geojson, {
                style: function (feature) {
                    return hcBarangayStyle(feature.properties);
                },
                onEachFeature: function (feature, layer) {
                    const p = feature.properties;
                    barangayLayers[p.name] = layer;

                    // Extra top-right padding so an auto-panned popup clears the
                    // Legend button + control column in that corner.
                    layer.bindPopup(hcBarangayInfoHtml(p), { autoPanPaddingTopRight: L.point(70, 20) });

                    // Hovering the shape itself surfaces its real case
                    // count AND which real diseases make up that count
                    // right away, without needing a click — a normal
                    // (non-permanent) tooltip, so Leaflet shows/hides it
                    // on mouseover/mouseout automatically; sticky:true
                    // lets it follow the cursor around the shape instead
                    // of staying pinned to one spot.
                    layer.bindTooltip(hcBarangayInfoHtml(p), { sticky: true, direction: 'top', className: 'barangay-hover-tooltip' });

                    layer.on('mouseover', function () {
                        if (layer !== selectedLayer) layer.setStyle(hcBarangayStyle(p, 'hover'));
                    });
                    layer.on('mouseout', function () {
                        hcRestyle(layer);
                    });
                    layer.on('click', function () {
                        layer.openPopup();
                    });
                    layer.on('popupopen', function () {
                        const previous = selectedLayer;
                        selectedLayer = layer;
                        if (previous && previous !== layer) hcRestyle(previous);
                        hcRestyle(layer);
                        layer.closeTooltip(); // the popup already shows the same info — don't stack both
                    });
                    layer.on('popupclose', function () {
                        if (selectedLayer === layer) selectedLayer = null;
                        hcRestyle(layer);
                    });
                    layer.on('tooltipopen', function () {
                        if (layer.isPopupOpen()) layer.closeTooltip();
                    });
                },
            }).addTo(map);

            // Extend the fit to include every facility marker too, so
            // none of them are ever cropped out of the initial view even
            // if one happens to sit right at the edge of the barangay
            // shapes.
            initialBounds = geoLayer.getBounds();
            facilityMarkers.forEach(function (marker) {
                initialBounds.extend(marker.getLatLng());
            });
            map.invalidateSize(); // real flex-stretched height may not be final yet when L.map() first ran — see this file's own comment on map init
            map.fitBounds(initialBounds, { padding: [30, 30] });

            // Centroid marker + permanent label for each barangay
            geojson.features.forEach(function (feature) {
                const p = feature.properties;
                const [lng, lat] = p.centroid;

                // White badge ringed in the barangay's intensity color
                // (gray when it has no cases) — small enough not to cover
                // its own label, boundary lines or neighbouring labels.
                const icon = L.divIcon({
                    className: '',
                    html: '<div class="barangay-marker' + (p.cases > 0 ? '' : ' is-empty') + '" style="--tier:' + p.color + '"><i class="bi bi-house-door-fill"></i></div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12],
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
                        offset: [0, 4],
                        className: 'barangay-label-tooltip',
                    }
                );
                // Same popup (and selected style) as clicking the shape itself
                marker.on('click', function () {
                    barangayLayers[p.name].openPopup(L.latLng(lat, lng));
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
        layer.setStyle(hcBarangayStyle(layer.feature.properties, 'strong'));
        layer.bringToFront();
        highlightResetTimer = setTimeout(function () {
            hcRestyle(layer);
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
