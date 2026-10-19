<!-- =============================================================
     ADD VACCINATION RECORD MODAL
     -------------------------------------------------------------
     Opened by medical-staff/vaccination_records.php's
     "+ Add Vaccination" button (#btnAddVaccination);
     assets/js/add-vaccination.js POSTs it to
     api/add-vaccination-record.php, then reloads the page.

     Records BOTH kinds of entries, chosen by Record Type:
       - Historical — received before registration and/or at another
         hospital/clinic/provider, entered from a vaccination card,
         old medical record, or patient-provided history. Nothing is
         auto-filled: Administered By / Administering Facility /
         Manufacturer may stay blank when unknown, and the date may
         be any day from birth to today.
       - Administered at this facility — Administering Facility is
         locked to the staff member's own facility and Administered By
         is pre-filled with their name (still editable), and the date
         can't predate the patient's registration.
     The date the entry was made is stored separately server-side
     (recordedOn/recordedBy), never in place of Date Administered.
     ============================================================= -->
<div class="modal fade adm-modal" id="addVaccinationModal" tabindex="-1" aria-labelledby="addVaccinationModalLabel" aria-hidden="true"
     data-staff-name="<?= htmlspecialchars($staffProfile['name']) ?>"
     data-staff-facility="<?= htmlspecialchars($staffProfile['facility']) ?>"
     data-birth-date="<?= htmlspecialchars($avxBirthDateRaw) ?>"
     data-registered-on="<?= htmlspecialchars($avxRegisteredRaw) ?>"
     data-today="<?= date('Y-m-d') ?>">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addVaccinationForm" novalidate>
        <input type="hidden" id="avxPatientId" value="<?= htmlspecialchars($patient['id']) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="addVaccinationModalLabel">Add Vaccination Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="addVaccinationError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="avxPatientName" class="form-label">Patient Name</label>
              <input type="text" class="form-control" id="avxPatientName" value="<?= htmlspecialchars($patient['name']) ?>" readonly tabindex="-1">
            </div>
            <div class="col-md-6 mb-3">
              <span class="form-label d-block" id="avxRecordTypeLabel">Record Type <span class="text-danger">*</span></span>
              <div role="radiogroup" aria-labelledby="avxRecordTypeLabel">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="avxRecordType" id="avxRecordTypeHistorical" value="historical" required>
                  <label class="form-check-label" for="avxRecordTypeHistorical">Historical Vaccination <span class="text-muted">(received before registration)</span></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="avxRecordType" id="avxRecordTypeHere" value="administered_here" required>
                  <label class="form-check-label" for="avxRecordTypeHere">Administered at <?= htmlspecialchars($staffProfile['facility']) ?></label>
                  <div class="invalid-feedback">Please choose a record type.</div>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-7 mb-3">
              <label for="avxName" class="form-label">Vaccine Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="avxName" list="avxNameOptions" placeholder="Type or pick a vaccine, e.g. Tetanus-Diphtheria (Td)" maxlength="120" autocomplete="off" required>
              <datalist id="avxNameOptions">
                <?php foreach ($vaccineNameSuggestions as $vaccineName): ?>
                <option value="<?= htmlspecialchars($vaccineName) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <div class="invalid-feedback">Please enter the vaccine name.</div>
            </div>
            <div class="col-md-5 mb-3">
              <label for="avxType" class="form-label">Vaccine Type <span class="text-danger">*</span></label>
              <select class="form-select" id="avxType" required>
                <option value="" selected disabled>Select vaccine type</option>
                <?php foreach ($vaccineTypeMeta as $typeKey => $typeMeta): ?>
                <option value="<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeMeta['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select the vaccine type.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="avxDateAdministered" class="form-label">Date Administered <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="avxDateAdministered" min="<?= htmlspecialchars($avxBirthDateRaw) ?>" max="<?= date('Y-m-d') ?>" required>
              <div class="form-text" id="avxDateHelp">The actual date the vaccine was given.</div>
              <div class="invalid-feedback" id="avxDateFeedback">Please enter a valid date administered.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="avxDose" class="form-label">Dose / Series <span class="text-danger">*</span></label>
              <select class="form-select" id="avxDose" required>
                <option value="" selected disabled>Select dose</option>
                <option value="1st Dose">1st Dose</option>
                <option value="2nd Dose">2nd Dose</option>
                <option value="3rd Dose">3rd Dose</option>
                <option value="4th Dose">4th Dose</option>
                <option value="Booster">Booster</option>
                <option value="Single Dose">Single Dose</option>
              </select>
              <div class="invalid-feedback">Please select the dose.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="avxNextDoseDue" class="form-label">Next Dose Due <span class="text-muted fw-normal">(Optional)</span></label>
              <input type="date" class="form-control" id="avxNextDoseDue">
              <div class="invalid-feedback">Must be after the date administered.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="avxManufacturer" class="form-label">Manufacturer <span class="text-muted fw-normal">(If known)</span></label>
              <input type="text" class="form-control" id="avxManufacturer" placeholder="e.g. Sanofi Pasteur" maxlength="120">
            </div>
            <div class="col-md-4 mb-3">
              <label for="avxGivenBy" class="form-label">Administered By <span class="text-muted fw-normal" id="avxGivenByHint">(If known)</span></label>
              <input type="text" class="form-control" id="avxGivenBy" placeholder="e.g. Nurse Maria Santos" maxlength="120">
              <div class="invalid-feedback">Please enter who administered the vaccine.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="avxFacility" class="form-label">Administering Facility <span class="text-muted fw-normal" id="avxFacilityHint">(If known)</span></label>
              <input type="text" class="form-control" id="avxFacility" placeholder="e.g. Ospital ng Maynila" maxlength="160">
            </div>
          </div>

          <div class="mb-0">
            <label for="avxNotes" class="form-label">Remarks / Source of Information <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea class="form-control" id="avxNotes" rows="2" maxlength="500" placeholder="e.g. Copied from patient's vaccination card; patient-reported, no card available"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="avxSubmitBtn"><i class="bi bi-check-lg"></i> Save Vaccination</button>
        </div>
      </form>
    </div>
  </div>
</div>
