<!-- =============================================================
     MEDICAL HISTORY — ADD/EDIT MODALS (Chronic Condition / Surgery /
     Family History)
     -------------------------------------------------------------
     Reused directly by subadmin/doctor/medical-staff's
     medical_history.php (same reuse pattern as
     includes/medical-staff/add_patient_modal.php — nothing here is
     role-specific). One modal per section, each doubling as both
     Add and Edit: assets/js/medical-history.js pre-fills the fields
     (and sets the hidden id) when opened from a row's pencil button,
     leaves them blank when opened from the section's own "+ Add"
     button. All three POST to api/add-*.php when the hidden id is
     empty, api/update-*.php otherwise.
     ============================================================= -->

<!-- ============ CHRONIC CONDITION MODAL ============ -->
<div class="modal fade adm-modal" id="mhxConditionModal" tabindex="-1" aria-labelledby="mhxConditionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="mhxConditionForm" novalidate>
        <input type="hidden" id="mhxConditionId">
        <input type="hidden" id="mhxConditionPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="mhxConditionModalLabel">Add Chronic Condition</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="mhxConditionError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="mhxConditionLabel" class="form-label">Condition</label>
            <input type="text" class="form-control" id="mhxConditionLabel" placeholder="e.g. Hypertension" required>
          </div>
          <div class="mb-3">
            <label for="mhxConditionDiagnosedOn" class="form-label">Date Diagnosed</label>
            <input type="date" class="form-control" id="mhxConditionDiagnosedOn" max="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label for="mhxConditionStatus" class="form-label">Status</label>
            <select class="form-select" id="mhxConditionStatus">
              <option value="Active">Active</option>
              <option value="Controlled">Controlled</option>
              <option value="Resolved">Resolved</option>
            </select>
          </div>
          <div class="mb-0">
            <label for="mhxConditionDetails" class="form-label">Notes <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea class="form-control" id="mhxConditionDetails" rows="2" placeholder="e.g. On maintenance medication"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="mhxConditionSubmitBtn"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ SURGERY / PROCEDURE MODAL ============ -->
<div class="modal fade adm-modal" id="mhxSurgeryModal" tabindex="-1" aria-labelledby="mhxSurgeryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="mhxSurgeryForm" novalidate>
        <input type="hidden" id="mhxSurgeryId">
        <input type="hidden" id="mhxSurgeryPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="mhxSurgeryModalLabel">Add Surgical History</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="mhxSurgeryError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="mhxSurgeryProcedure" class="form-label">Procedure</label>
            <input type="text" class="form-control" id="mhxSurgeryProcedure" placeholder="e.g. Appendectomy" required>
          </div>
          <div class="mb-3">
            <label for="mhxSurgeryDate" class="form-label">Date <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="date" class="form-control" id="mhxSurgeryDate" max="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label for="mhxSurgeryFacility" class="form-label">Surgeon / Facility <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="text" class="form-control" id="mhxSurgeryFacility" placeholder="e.g. Dr. Juan Dela Cruz, Lipa City Medical Center">
          </div>
          <div class="mb-0">
            <label for="mhxSurgeryDetails" class="form-label">Notes <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea class="form-control" id="mhxSurgeryDetails" rows="2" placeholder="e.g. No complications"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="mhxSurgerySubmitBtn"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ FAMILY HISTORY MODAL ============ -->
<div class="modal fade adm-modal" id="mhxFamilyModal" tabindex="-1" aria-labelledby="mhxFamilyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="mhxFamilyForm" novalidate>
        <input type="hidden" id="mhxFamilyId">
        <input type="hidden" id="mhxFamilyPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="mhxFamilyModalLabel">Add Family History</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="mhxFamilyError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="mhxFamilyCondition" class="form-label">Condition</label>
            <input type="text" class="form-control" id="mhxFamilyCondition" placeholder="e.g. Diabetes Mellitus" required>
          </div>
          <div class="mb-0">
            <label for="mhxFamilyRelation" class="form-label">Relation</label>
            <input type="text" class="form-control" id="mhxFamilyRelation" placeholder="e.g. Mother, Father, Maternal Aunt" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="mhxFamilySubmitBtn"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
