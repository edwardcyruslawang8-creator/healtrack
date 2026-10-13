<!-- =============================================================
     ADD NEW FACILITY MODAL
     -------------------------------------------------------------
     Shared by admin_dashboard.php ("Add Facility") and
     facility_management.php ("Add New Facility") — both buttons open
     this same modal (see assets/js/add-facility.js).

     FRONT-END ONLY for now — submitting this form appends a new
     facility card/row directly into the current page's DOM. It does
     not persist anywhere yet; once facility CRUD + Firestore are
     connected, the submit handler in add-facility.js would POST to an
     api/ endpoint instead and this markup wouldn't need to change.
     ============================================================= -->
<div class="modal fade adm-modal" id="addFacilityModal" tabindex="-1" aria-labelledby="addFacilityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addFacilityForm" novalidate>
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

          <div class="mb-0">
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
