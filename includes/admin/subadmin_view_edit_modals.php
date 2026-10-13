<!-- =============================================================
     VIEW / EDIT SUB-ADMINISTRATOR MODALS
     -------------------------------------------------------------
     Triggered by the "View"/"Edit" action buttons on each row of
     admin/subadmin_management.php's table (both carry data-id
     attributes; assets/js/subadmin-actions.js looks the row up in
     window.SUBADMINS_DATA to populate these). Editing POSTs to
     api/update-subadmin.php, which merges the changes into the
     existing Firestore users/{uid} document (name, facility, role,
     status — not email/password, which aren't editable here).

     $facilities, $roleOptions, $statusLegend, $statusOptions must be
     in scope wherever this is included — see
     includes/subadmin_management_firestore.php.
     ============================================================= -->

<!-- ============ VIEW MODAL (read-only) ============ -->
<div class="modal fade adm-modal" id="viewSubadminModal" tabindex="-1" aria-labelledby="viewSubadminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewSubadminModalLabel">Sub-Administrator Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="sam-view-row"><span class="sam-view-label">Name</span><span class="sam-view-value" id="viewSubadminName"></span></div>
        <div class="sam-view-row"><span class="sam-view-label">Email</span><span class="sam-view-value" id="viewSubadminEmail"></span></div>
        <div class="sam-view-row"><span class="sam-view-label">Facility</span><span class="sam-view-value" id="viewSubadminFacility"></span></div>
        <div class="sam-view-row"><span class="sam-view-label">Role</span><span class="sam-view-value" id="viewSubadminRole"></span></div>
        <div class="sam-view-row"><span class="sam-view-label">Status</span><span class="sam-view-value" id="viewSubadminStatus"></span></div>
        <div class="sam-view-row"><span class="sam-view-label">Last Login</span><span class="sam-view-value" id="viewSubadminLastLogin"></span></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ EDIT MODAL ============ -->
<div class="modal fade adm-modal" id="editSubadminModal" tabindex="-1" aria-labelledby="editSubadminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editSubadminForm" novalidate>
        <input type="hidden" id="editSubadminId">
        <div class="modal-header">
          <h5 class="modal-title" id="editSubadminModalLabel">Edit Sub-Administrator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="editSubadminError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="editSubadminName" class="form-label">Username</label>
            <input type="text" class="form-control" id="editSubadminName" required>
            <div class="invalid-feedback">Please enter a username.</div>
          </div>

          <div class="mb-3">
            <label for="editSubadminFacility" class="form-label">Assigned Facility</label>
            <select class="form-select" id="editSubadminFacility">
              <?php foreach ($facilities as $f): ?>
              <option value="<?= htmlspecialchars($f['id']) ?>"><?= htmlspecialchars($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="editSubadminRole" class="form-label">Role</label>
            <select class="form-select" id="editSubadminRole">
              <?php foreach ($roleOptions as $role): ?>
              <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-0">
            <label for="editSubadminStatus" class="form-label">Status</label>
            <select class="form-select" id="editSubadminStatus">
              <?php foreach ($statusOptions as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusLegend[$status]['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="editSubadminSubmitBtn">
            <i class="bi bi-check-lg"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
