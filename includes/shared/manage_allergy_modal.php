<!-- =============================================================
     MANAGE ALLERGY MODAL (shared — doctor/allergies.php AND
     medical-staff/allergies.php; NOT subadmin, which stays read-only)
     -------------------------------------------------------------
     One modal, one endpoint (api/update-allergy-clinical-status.php)
     handles every clinical-status transition. Opened two ways by
     assets/js/allergy-actions.js, both via the same
     [data-allergy-manage] delegated handler:
       - "Manage Allergy" button on an Active row (data-allergy-manage="manage")
       - "Reactivate Allergy" button on an Inactive/Resolved row
         (data-allergy-manage="reactivate", pre-selects New Clinical
         Status = Active, but any status is still selectable)
     The top section (allergen/type/reaction/severity/onset date/current
     statuses) is READ-ONLY, populated purely from the clicked row's
     data-* attributes — the original allergy information is never
     editable here, only the new clinical/verification status + the
     required documented assessment that justifies the change.
     ============================================================= -->
<div class="modal fade adm-modal alg-manage-modal" id="manageAllergyModal" tabindex="-1" aria-labelledby="manageAllergyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="manageAllergyForm" novalidate enctype="multipart/form-data">
        <input type="hidden" id="malAllergyId">
        <div class="modal-header">
          <h5 class="modal-title" id="manageAllergyModalLabel">Manage Allergy</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="manageAllergyError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="alg-manage-readonly">
            <div class="alg-manage-readonly-head">
              <span class="alg-type-icon" id="malTypeIcon"><i class="bi"></i></span>
              <div>
                <div class="alg-strong" id="malAllergenName"></div>
                <div class="alg-subtype" id="malTypeLabel"></div>
              </div>
            </div>
            <div class="alg-manage-readonly-grid">
              <div><span class="alg-field-label">Reaction</span><span id="malReaction"></span></div>
              <div><span class="alg-field-label">Severity</span><span id="malSeverity" class="alg-badge"></span></div>
              <div><span class="alg-field-label">Onset Date</span><span id="malOnsetDate"></span></div>
              <div><span class="alg-field-label">Current Clinical Status</span><span id="malCurrentClinicalStatus" class="alg-badge"></span></div>
              <div><span class="alg-field-label">Current Verification Status</span><span id="malCurrentVerificationStatus" class="alg-badge alg-badge-verif"></span></div>
            </div>
          </div>

          <hr>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="malNewClinicalStatus" class="form-label">New Clinical Status <span class="text-danger">*</span></label>
              <select class="form-select" id="malNewClinicalStatus" required>
                <option value="" selected disabled>Select status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="resolved">Resolved</option>
              </select>
              <div class="invalid-feedback">Please select the new clinical status.</div>
            </div>
            <div class="col-md-6">
              <label for="malNewVerificationStatus" class="form-label">Verification Status <span class="text-danger">*</span></label>
              <select class="form-select" id="malNewVerificationStatus" required>
                <option value="" selected disabled>Select status</option>
                <option value="unconfirmed">Unconfirmed</option>
                <option value="presumed">Presumed</option>
                <option value="confirmed">Confirmed</option>
                <option value="refuted">Refuted</option>
                <option value="entered-in-error">Entered in Error</option>
              </select>
              <div class="invalid-feedback">Please select a verification status.</div>
            </div>

            <div class="col-md-6">
              <label for="malAssessmentDate" class="form-label">Assessment Date <span class="text-danger">*</span></label>
              <input type="date" data-hc-datepicker class="form-control" id="malAssessmentDate" required>
              <div class="invalid-feedback">Enter a date between the allergy's onset date and today.</div>
            </div>
            <div class="col-md-6">
              <label for="malAssessedBy" class="form-label">Assessed By</label>
              <input type="text" class="form-control" id="malAssessedBy" disabled>
            </div>

            <div class="col-12">
              <label for="malReason" class="form-label">Clinical Assessment / Reason for Status Change <span class="text-danger">*</span></label>
              <textarea class="form-control" id="malReason" rows="3" maxlength="1000" required placeholder="Document the clinical basis for this change, e.g. skin-prick test negative on repeat challenge, no reaction on re-exposure under supervision, etc."></textarea>
              <div class="form-text">Required. This becomes part of the allergy's permanent audit history and cannot be edited afterward.</div>
              <div class="invalid-feedback">Please document the clinical assessment or reason for this change.</div>
            </div>

            <div class="col-12">
              <label for="malAttachment" class="form-label">Supporting Documentation <span class="text-muted fw-normal">(Optional)</span></label>
              <input type="file" class="form-control" id="malAttachment" accept=".pdf,.jpg,.jpeg,.png">
              <div class="form-text">PDF, JPG, or PNG, up to 10MB — e.g. allergy test results, specialist referral notes.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="malSubmitBtn"><i class="bi bi-check-lg"></i> Save Status Change</button>
        </div>
      </form>
    </div>
  </div>
</div>
