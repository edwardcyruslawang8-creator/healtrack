<!-- =============================================================
     ADD NEW FACILITY MODAL
     -------------------------------------------------------------
     Shared by admin_dashboard.php ("Add Facility") and
     facility_management.php ("Add New Facility") — both buttons open
     this same modal. Submitting POSTs to api/create-facility.php,
     which writes a real Firestore "facilities" doc (see
     assets/js/add-facility.js).

     The map (Leaflet, same library/tiles as admin/gis_mapping.php) is
     a real click-to-place picker, not decorative — a pin is required
     before the form can submit, and both this page's JS AND
     api/create-facility.php itself (never trusting the client alone)
     reject a point outside the real Lipa City boundary
     (assets/data/lipa_city_boundary.geojson) — this app's whole real
     coverage area. The placed pin shows up on admin/gis_mapping.php
     and patient/gis.php once saved (see
     includes/firestore/facilities.php's hc_get_all_facilities_summary()).

     "This facility is integrated with HEALTrack" is a separate,
     honest flag — a facility can have a real pin without actually
     having the system running there yet. Only an integrated facility's
     "View Analytics" link is clickable on facility_management.php.
     ============================================================= -->
<div class="modal fade adm-modal" id="addFacilityModal" tabindex="-1" aria-labelledby="addFacilityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:min(1000px, 92vw);">
    <div class="modal-content">
      <form id="addFacilityForm" novalidate>
        <input type="hidden" id="facilityLat" name="facilityLat">
        <input type="hidden" id="facilityLng" name="facilityLng">
        <div class="modal-header">
          <h5 class="modal-title" id="addFacilityModalLabel">Add New Facility</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="facilityName" class="form-label">Facility Name</label>
            <input type="text" class="form-control" id="facilityName" name="facilityName" placeholder="e.g. South Super Health Center" required>
            <div class="invalid-feedback">Please enter a facility name.</div>
            <div class="form-text">Every facility in HEALTrack is a Super Health Center — type which one (e.g. South, Central).</div>
          </div>

          <div class="mb-3">
            <label for="facilityLocation" class="form-label">Location / Address</label>
            <input type="text" class="form-control" id="facilityLocation" name="facilityLocation" placeholder="e.g. P. Torres St, Lipa City" required>
            <div class="invalid-feedback">Please enter a location.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Pin Location on Map <span class="text-muted fw-normal">(Lipa City only)</span></label>
            <div id="addFacilityMap" data-boundary-url="<?= asset_url('/assets/data/lipa_city_boundary.geojson') ?>" style="height:280px; border-radius:10px; border:1px solid var(--adm-border);"></div>
            <div class="form-text">Click anywhere inside Lipa City to drop a pin. Drag it afterward to fine-tune.</div>
            <div id="addFacilityMapError" class="small text-danger mt-1 d-none"></div>
          </div>

          <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="facilityIntegrated" name="facilityIntegrated">
            <label class="form-check-label" for="facilityIntegrated">This facility is integrated with HEALTrack</label>
            <div class="form-text">Enables its "View Analytics" link. Leave unchecked until the system is actually set up there.</div>
          </div>

          <div class="mb-0 mt-3">
            <label for="facilityStatus" class="form-label">Status</label>
            <select class="form-select" id="facilityStatus" name="facilityStatus">
              <option value="active" selected>Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit">
            <i class="bi bi-plus-lg"></i> Add Facility
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
