<!-- =============================================================
     ADD SUB-ADMINISTRATOR MODAL
     -------------------------------------------------------------
     Triggered by admin/subadmin_management.php's "Add Sub-Administrator"
     button (#btnAddSubadmin). Submitting this form POSTs to
     api/create-subadmin.php, which creates a real Firebase Auth account
     server-side (via the Admin SDK, so it doesn't touch the signing-in
     admin's own session) plus a matching Firestore users/{uid} profile
     document with role=subadmin — see assets/js/add-subadmin.js for the
     request/response handling.

     $facilities must be in scope wherever this is included — see
     includes/subadmin_management_firestore.php, which requires
     facility_management_firestore.php for exactly that.
     ============================================================= -->
<div class="modal fade adm-modal" id="addSubadminModal" tabindex="-1" aria-labelledby="addSubadminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addSubadminForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="addSubadminModalLabel">Add Sub-Administrator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="addSubadminError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="subadminUsername" class="form-label">Name</label>
            <input type="text" class="form-control" id="subadminUsername" name="username" placeholder="e.g. Maria Santos" required>
            <div class="invalid-feedback">Please enter a name.</div>
          </div>

          <div class="mb-3">
            <label for="subadminEmail" class="form-label">Email</label>
            <input type="email" class="form-control" id="subadminEmail" name="email" placeholder="e.g. maria.santos@northsuperhc.com" required>
            <div class="invalid-feedback">Please enter a valid email address.</div>
          </div>

          <div class="mb-3">
            <label for="subadminFacility" class="form-label">Assigned Facility</label>
            <select class="form-select" id="subadminFacility" name="facilityId" required>
              <?php foreach ($facilities as $f): ?>
              <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="subadminPassword" class="form-label">Password</label>
            <div class="adm-password-wrap">
              <input type="password" class="form-control" id="subadminPassword" name="password" placeholder="At least 6 characters" minlength="6" required>
              <button type="button" class="adm-password-toggle" data-target="subadminPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="invalid-feedback">Password must be at least 6 characters.</div>
          </div>

          <div class="mb-0">
            <label for="subadminConfirmPassword" class="form-label">Confirm Password</label>
            <div class="adm-password-wrap">
              <input type="password" class="form-control" id="subadminConfirmPassword" name="confirmPassword" placeholder="Re-enter password" minlength="6" required>
              <button type="button" class="adm-password-toggle" data-target="subadminConfirmPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="invalid-feedback">Passwords do not match.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="subadminSubmitBtn">
            <i class="bi bi-plus-lg"></i> Add Sub-Administrator
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
