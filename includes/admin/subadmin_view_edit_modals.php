<!-- =============================================================
     MANAGE ACCOUNT / EDIT SUB-ADMINISTRATOR MODALS
     -------------------------------------------------------------
     Triggered by each row's single "Manage Account" button on
     admin/subadmin_management.php's table (data-id attribute;
     assets/js/subadmin-actions.js looks the row up in
     window.SUBADMINS_DATA to populate these). Editing POSTs to
     api/update-subadmin.php, which merges the changes into the
     existing Firestore users/{uid} document (name, facility, role,
     status — not email/password, which aren't editable here).

     $facilities, $roleOptions, $statusLegend, $statusOptions must be
     in scope wherever this is included — see
     includes/subadmin_management_firestore.php.
     ============================================================= -->

<!-- ============ MANAGE ACCOUNT MODAL ============
     Single entry point replacing the old row of bare action icons —
     opens on "Manage Account", shows this account's real details
     (Name/Email/Facility/Role/Status/Last Login — what the old
     separate View modal showed, folded in here instead of needing its
     own extra click) plus the real management actions below them. One
     shared modal for every row (assets/js/subadmin-actions.js
     populates every #manageSubadmin* field and each [data-manage-action]
     button's data-id on open). Edit Account is ALWAYS offered
     regardless of status — an admin needs to be able to manage a
     locked/inactive/pending account too (reassign its facility/role,
     fix a typo'd name, etc.), not just an already-active one,
     previously the one real gap here. Unlock Account only appears when
     actually locked (same rule as before — it does more than Edit's
     own Status field could: see api/unlock-subadmin.php's header
     comment on why it's a separate, more complete action). Picking
     Edit/Unlock/Delete here closes this modal and opens the real Edit
     modal or runs the real confirm+POST flow — same endpoints as
     before. -->
<div class="modal fade adm-modal sam-manage-modal" id="manageSubadminModal" tabindex="-1" aria-labelledby="manageSubadminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageSubadminModalLabel">Manage Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="sam-manage-who">
          <span class="sam-manage-who-name" id="manageSubadminName"></span>
          <span class="sam-manage-who-email" id="manageSubadminEmail"></span>
        </div>
        <div class="sam-manage-details">
          <div class="sam-view-row"><span class="sam-view-label">Facility</span><span class="sam-view-value" id="manageSubadminFacility"></span></div>
          <div class="sam-view-row"><span class="sam-view-label">Role</span><span class="sam-view-value" id="manageSubadminRole"></span></div>
          <div class="sam-view-row"><span class="sam-view-label">Status</span><span class="sam-view-value" id="manageSubadminStatus"></span></div>
          <div class="sam-view-row"><span class="sam-view-label">Last Login</span><span class="sam-view-value" id="manageSubadminLastLogin"></span></div>
        </div>
        <div class="sam-manage-actions">
          <button type="button" class="sam-manage-action-row" data-manage-action="edit">
            <i class="bi bi-pencil"></i> Edit Account
          </button>
          <button type="button" class="sam-manage-action-row" data-manage-action="continue">
            <i class="bi bi-unlock-fill"></i> Unlock Account
          </button>
          <button type="button" class="sam-manage-action-row danger" data-manage-action="delete">
            <i class="bi bi-trash"></i> Delete Account
          </button>
        </div>
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
