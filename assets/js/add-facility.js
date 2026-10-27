/* =============================================================
   ADD NEW FACILITY MODAL JS
   ---------------------------------------------------------------
   Shared by admin_dashboard.php and facility_management.php — both
   pages load this file alongside includes/add_facility_modal.php.
   Wires up every "Add Facility" trigger button to open the modal,
   validates the form, then POSTs to api/create-facility.php, which
   writes a real Firestore "facilities" document. On success the page
   reloads so the card grid / Facility Overview table re-render from
   that live data instead of guessing what the new row should look
   like.

   The map picker (Leaflet) draws the real Lipa City boundary
   (assets/data/lipa_city_boundary.geojson, fetched once) and only
   accepts a click inside it — same ray-casting point-in-polygon logic
   as includes/firestore/facilities.php's hc_point_in_lipa_city(), kept
   in sync deliberately (this is a convenience check for instant
   feedback; api/create-facility.php re-checks server-side and never
   trusts this alone).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addFacilityModal');
    const form = document.getElementById('addFacilityForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const submitBtn = form.querySelector('.adm-modal-btn-submit');
    const submitBtnDefaultHtml = submitBtn ? submitBtn.innerHTML : '';
    const latField = document.getElementById('facilityLat');
    const lngField = document.getElementById('facilityLng');
    const mapErrorEl = document.getElementById('addFacilityMapError');

    let errorBox = document.getElementById('addFacilityError');
    if (!errorBox) {
        errorBox = document.createElement('div');
        errorBox.id = 'addFacilityError';
        errorBox.className = 'alert alert-danger py-2 px-3 small d-none';
        errorBox.setAttribute('role', 'alert');
        form.querySelector('.modal-body').prepend(errorBox);
    }

    // ---------------------------------------------------------------
    // Ray-casting point-in-polygon — same algorithm as
    // includes/firestore/facilities.php's hc_point_in_lipa_city().
    // ---------------------------------------------------------------
    function pointInPolygon(lat, lng, ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const xi = ring[i][0];
            const yi = ring[i][1];
            const xj = ring[j][0];
            const yj = ring[j][1];
            const intersects = ((yi > lat) !== (yj > lat))
                && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
            if (intersects) inside = !inside;
        }
        return inside;
    }

    // ---------------------------------------------------------------
    // Map picker — created once, lazily, the first time the modal is
    // actually shown (Leaflet needs a visible container with real
    // dimensions to size itself correctly).
    // ---------------------------------------------------------------
    let map = null;
    let marker = null;
    let boundaryRing = null; // [[lng, lat], ...], filled in once the GeoJSON fetch below resolves

    function setPin(lat, lng) {
        latField.value = lat;
        lngField.value = lng;
        if (mapErrorEl) mapErrorEl.classList.add('d-none');

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function () {
                const pos = marker.getLatLng();
                if (boundaryRing && !pointInPolygon(pos.lat, pos.lng, boundaryRing)) {
                    // Dragged outside Lipa City — snap back to the last valid spot.
                    marker.setLatLng([latField.value, lngField.value]);
                    showMapError('Please keep the pin within Lipa City.');
                    return;
                }
                latField.value = pos.lat;
                lngField.value = pos.lng;
            });
        }
    }

    function showMapError(message) {
        if (!mapErrorEl) return;
        mapErrorEl.textContent = message;
        mapErrorEl.classList.remove('d-none');
    }

    function initMap() {
        if (map || typeof L === 'undefined') return;
        const mapEl = document.getElementById('addFacilityMap');
        if (!mapEl) return;

        const LIPA_CENTER = [13.9411, 121.1622];
        map = L.map('addFacilityMap').setView(LIPA_CENTER, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map);

        fetch(mapEl.dataset.boundaryUrl)
            .then(function (res) { return res.json(); })
            .then(function (geojson) {
                boundaryRing = geojson.geometry.coordinates[0];
                const boundaryLayer = L.geoJSON(geojson, {
                    style: { color: '#8B1E22', weight: 2, fillOpacity: 0.05 },
                }).addTo(map);
                map.fitBounds(boundaryLayer.getBounds(), { padding: [10, 10] });
            })
            .catch(function () {
                // Boundary failed to load — the server-side check in
                // api/create-facility.php still enforces it either way,
                // this just means no visual guide/instant feedback.
            });

        map.on('click', function (e) {
            if (boundaryRing && !pointInPolygon(e.latlng.lat, e.latlng.lng, boundaryRing)) {
                showMapError('Please pin a location within Lipa City.');
                return;
            }
            setPin(e.latlng.lat, e.latlng.lng);
        });
    }

    document.querySelectorAll('#btnAddFacility, .btn-add-facility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            if (mapErrorEl) mapErrorEl.classList.add('d-none');
            latField.value = '';
            lngField.value = '';
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
            modal.show();
        });
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        initMap();
        if (map) map.invalidateSize();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        if (!latField.value || !lngField.value) {
            showMapError('Please pin this facility\'s location on the map.');
            return;
        }

        const payload = {
            name: document.getElementById('facilityName').value.trim(),
            location: document.getElementById('facilityLocation').value.trim(),
            status: document.getElementById('facilityStatus').value,
            lat: parseFloat(latField.value),
            lng: parseFloat(lngField.value),
            isIntegrated: document.getElementById('facilityIntegrated').checked,
        };

        errorBox.classList.add('d-none');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding…';
        }

        fetch('../api/create-facility.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnDefaultHtml;
                }
            });
    });
});
