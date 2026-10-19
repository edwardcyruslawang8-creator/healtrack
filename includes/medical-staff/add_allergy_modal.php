<!-- =============================================================
     ADD ALLERGY RECORD MODAL
     -------------------------------------------------------------
     Opened by medical-staff/allergies.php's "+ Add Allergy" button
     (#btnAddAllergy); assets/js/allergy-actions.js POSTs it to
     api/create-allergy-record.php, then reloads the page so the new
     record shows in the right table and the summary cards recount.
     Status defaults to Active; "Inactive / Resolved" is there for
     recording a past allergy the patient has since outgrown.

     Layout: a single 2-column grid with uniform gutters (row g-3) —
     the allergen, reaction, and notes span the full width; the short
     pick-one fields pair up side by side. Sized by
     #addAllergyModal .modal-dialog in assets/css/allergies.css.
     ============================================================= -->
<div class="modal fade adm-modal" id="addAllergyModal" tabindex="-1" aria-labelledby="addAllergyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="addAllergyForm" novalidate>
        <input type="hidden" id="aalPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addAllergyModalLabel">Add Allergy Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="addAllergyError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="row g-3">
            <div class="col-12">
              <label for="aalAllergen" class="form-label">Allergen <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="aalAllergen" placeholder="e.g. Dust mites, Pollen, Penicillin, Shellfish" maxlength="120" autocomplete="off" required>
              <div class="invalid-feedback">Please enter the allergen.</div>
            </div>

            <div class="col-md-6">
              <label for="aalType" class="form-label">Allergy Type <span class="text-danger">*</span></label>
              <select class="form-select" id="aalType" required>
                <option value="" selected disabled>Select type</option>
                <?php foreach ($allergyTypeOptions as $typeOption): ?>
                <option value="<?= htmlspecialchars($typeOption) ?>"><?= htmlspecialchars($typeOption) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select the allergy type.</div>
            </div>
            <div class="col-md-6">
              <label for="aalSeverity" class="form-label">Severity <span class="text-danger">*</span></label>
              <select class="form-select" id="aalSeverity" required>
                <option value="" selected disabled>Select severity</option>
                <option value="mild">Mild</option>
                <option value="moderate">Moderate</option>
                <option value="severe">Severe</option>
              </select>
              <div class="invalid-feedback">Please select the severity.</div>
            </div>

            <div class="col-12">
              <label for="aalReaction" class="form-label">Reaction <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="aalReaction" placeholder="e.g. Sneezing, skin rash, swelling, difficulty breathing" maxlength="200" required>
              <div class="invalid-feedback">Please describe the reaction.</div>
            </div>

            <div class="col-md-6">
              <label for="aalOnsetDate" class="form-label">Onset Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="aalOnsetDate" min="<?= htmlspecialchars($algBirthDateRaw) ?>" max="<?= date('Y-m-d') ?>" aria-describedby="aalOnsetHelp" required>
              <div class="form-text" id="aalOnsetHelp">When it was first identified or reported.</div>
              <div class="invalid-feedback">Enter a date between the patient's birth date and today.</div>
            </div>
            <div class="col-md-6">
              <label for="aalStatus" class="form-label">Status <span class="text-danger">*</span></label>
              <select class="form-select" id="aalStatus" required>
                <option value="active" selected>Active</option>
                <option value="resolved">Inactive / Resolved</option>
              </select>
            </div>

            <div class="col-12">
              <label for="aalNotes" class="form-label">Notes <span class="text-muted fw-normal">(Optional)</span></label>
              <textarea class="form-control" id="aalNotes" rows="3" maxlength="500" placeholder="e.g. Confirmed by skin test; carries an epinephrine auto-injector"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="aalSubmitBtn"><i class="bi bi-check-lg"></i> Save Allergy</button>
        </div>
      </form>
    </div>
  </div>
</div>
