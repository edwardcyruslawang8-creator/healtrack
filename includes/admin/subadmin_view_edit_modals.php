<!-- =============================================================
     MANAGE ACCOUNT MODAL
     -------------------------------------------------------------
     Triggered by each row's single "Manage Account" button on
     admin/subadmin_management.php's table (data-id attribute;
     assets/js/subadmin-actions.js looks the row up in
     window.SUBADMINS_DATA to populate this). Shows this account's real
     details (Name/Email/Facility/Role/Last Login) plus the real
     management actions below them.

     There's no general "Edit Account" here (by design — username,
     facility, and role are permanent once a sub-administrator is
     created; only Firebase Auth's own password-reset flow or deleting
     and re-adding the account can change them). The one thing an admin
     CAN change post-creation is Active/Inactive status, via the real
     switch below — POSTs to api/update-subadmin-status.php. It's
     disabled (with a tooltip) when the account is actually "locked" or
     "pending" — those aren't an admin's to flip directly: Unlock
     Account (only offered when locked — it resets the lockout counter
     too, which a plain status flip alone wouldn't do) or the real
     pending-invitation flow handle those instead.
     ============================================================= -->
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

        <div class="sam-status-toggle-row" id="manageSubadminStatusToggleRow">
          <span class="sam-status-toggle-label" id="manageSubadminStatusToggleLabel">Active</span>
          <div class="form-check form-switch sam-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="manageSubadminStatusToggle">
          </div>
        </div>

        <div class="sam-manage-actions">
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
