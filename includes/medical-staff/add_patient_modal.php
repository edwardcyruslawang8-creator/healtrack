<!-- =============================================================
     ADD PATIENT MODAL — multi-step intake wizard
     -------------------------------------------------------------
     Triggered by medical-staff/patient_management.php's "Add Patient"
     button (#btnAddPatient). Styled by assets/css/add_patient_modal.css
     ("apm-" prefix), driven by assets/js/add-patient.js.

     Step 1 (Personal Information) fields map straight onto the real
     "patients" Firestore collection — same collection every role's
     Patient Management page reads via includes/firestore/patients.php.
     Barangay options are the 11 real Lipa City barangays this clinic
     actually covers (Balintawak, Bugtong, Buclanin, Dagatan, Inosluban,
     Lumbang, Marawoy, Plaridel, Pusil, San Lucas, Talisay) — the SAME
     list patient/gis.php's case-count map now uses too
     (includes/shared/gis_geo.php, includes/public/gis_data_firestore.php),
     after that map's own case counts were connected to real data. City/
     Municipality has no such dataset yet so it stays a free-text field.

     Steps 2-4 (Vital Signs, History & Allergies, Medications) are
     genuinely optional intake data: vitals are stored on the new
     patient doc; known conditions/allergies/medications each become
     their own real "medical_conditions"/"allergies"/"medications"
     documents (same collections includes/firestore/medical_records.php,
     allergies.php, and medications.php read for the Patient Profile
     tabs), tied to the new patientId — filled in with the same
     default field shape hc_empty_allergy()/hc_get_medications_for_patient()
     expect so those tabs don't break when this patient is later viewed.

     Step 5's Reason for Visit is stored as-entered on the patient doc
     (initialReasonForVisit) — there's still no "consultations"
     collection (see medical-staff/consultations.php's header comment),
     so this does NOT create a consultation record, just keeps what the
     staff member actually typed at intake.

     Step 4's Medication Name field suggests real drug names from the
     same "medicines" Firestore collection subadmin/pharmacist Medicine
     & Vaccine Inventory pages read (includes/subadmin/medicine_vaccine_inventory_firestore.php),
     via a <datalist> rather than a closed <select> — a patient's
     CURRENT medications may include drugs prescribed elsewhere, outside
     this clinic's own inventory, so the field stays free-text with
     real suggestions rather than a fabricated or overly-restrictive
     fixed list.
     ============================================================= -->
<?php
$apmMedicineNames = [];
foreach ($firestore->listDocuments('medicines') as $apmMedDoc) {
    $apmMedName = trim((string) ($apmMedDoc['fields']['name'] ?? ''));
    if ($apmMedName !== '') {
        $apmMedicineNames[$apmMedName] = true;
    }
}
$apmMedicineNames = array_keys($apmMedicineNames);
sort($apmMedicineNames, SORT_STRING | SORT_FLAG_CASE);
?>
<datalist id="apmMedicineOptions">
    <?php foreach ($apmMedicineNames as $apmMedName): ?>
    <option value="<?= htmlspecialchars($apmMedName) ?>"></option>
    <?php endforeach; ?>
</datalist>
<script>window.APM_STAFF_NAME = <?= json_encode($_SESSION['name'] ?? '') ?>;</script>
<div class="modal fade apm-modal" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addPatientModalLabel">Add New Patient</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="addPatientError" class="alert alert-danger py-2 px-3 small d-none mt-3" role="alert"></div>

        <!-- Stepper -->
        <div class="apm-stepper" id="apmStepper">
          <div class="apm-step active" data-step="1"><i class="bi bi-person-fill"></i><span>Personal Information</span></div>
          <div class="apm-step" data-step="2"><i class="bi bi-heart-pulse-fill"></i><span>Vital Signs</span></div>
          <div class="apm-step" data-step="3"><i class="bi bi-clipboard2-pulse-fill"></i><span>History &amp; Allergies</span></div>
          <div class="apm-step" data-step="4"><i class="bi bi-capsule"></i><span>Medications</span></div>
          <div class="apm-step" data-step="5"><i class="bi bi-chat-left-text-fill"></i><span>Reason for Visit</span></div>
        </div>

        <form id="addPatientForm" novalidate>

          <!-- ============ STEP 1: PERSONAL INFORMATION ============ -->
          <div class="apm-step-panel active" data-panel="1">

            <div class="apm-section">
              <h6 class="apm-section-title">Personal Information</h6>
              <div class="apm-grid apm-grid-3">
                <div class="apm-field">
                  <label for="apmFirstName">First Name<span class="req">*</span></label>
                  <input type="text" class="form-control" id="apmFirstName" placeholder="Enter first name" required>
                  <div class="invalid-feedback">Required.</div>
                </div>
                <div class="apm-field">
                  <label for="apmMiddleName">Middle Name</label>
                  <input type="text" class="form-control" id="apmMiddleName" placeholder="Enter middle name">
                </div>
                <div class="apm-field">
                  <label for="apmLastName">Last Name<span class="req">*</span></label>
                  <input type="text" class="form-control" id="apmLastName" placeholder="Enter last name" required>
                  <div class="invalid-feedback">Required.</div>
                </div>
              </div>
              <div class="apm-grid apm-grid-3">
                <div class="apm-field">
                  <label for="apmBirthDate">Date of Birth<span class="req">*</span></label>
                  <input type="date" class="form-control" id="apmBirthDate" max="<?= date('Y-m-d') ?>" required>
                  <div class="invalid-feedback">Please enter a valid birth date.</div>
                </div>
                <div class="apm-field">
                  <label for="apmAge">Age</label>
                  <input type="text" class="form-control" id="apmAge" placeholder="Auto-calculated" readonly>
                </div>
                <div class="apm-field">
                  <label for="apmSex">Sex<span class="req">*</span></label>
                  <select class="form-select" id="apmSex" required>
                    <option value="" selected disabled>Select sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                  <div class="invalid-feedback">Required.</div>
                </div>
              </div>
              <div class="apm-grid apm-grid-2">
                <div class="apm-field">
                  <label for="apmCivilStatus">Civil Status<span class="req">*</span></label>
                  <select class="form-select" id="apmCivilStatus" required>
                    <option value="" selected disabled>Select civil status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                  </select>
                  <div class="invalid-feedback">Required.</div>
                </div>
                <div class="apm-field">
                  <label for="apmOccupation">Occupation</label>
                  <input type="text" class="form-control" id="apmOccupation" placeholder="Enter occupation">
                </div>
              </div>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Contact Information</h6>
              <div class="apm-field">
                <label for="apmPhone">Phone Number<span class="req">*</span></label>
                <input type="text" class="form-control" id="apmPhone" placeholder="Enter phone number" required>
                <div class="invalid-feedback">Required.</div>
              </div>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Address Information</h6>
              <div class="apm-field mb-3">
                <label for="apmAddress">Address<span class="req">*</span></label>
                <textarea class="form-control" id="apmAddress" rows="2" placeholder="Enter complete address" required></textarea>
                <div class="invalid-feedback">Required.</div>
              </div>
              <div class="apm-grid apm-grid-2">
                <div class="apm-field">
                  <label for="apmBarangay">Barangay<span class="req">*</span></label>
                  <select class="form-select" id="apmBarangay" required>
                    <option value="" selected disabled>Select barangay</option>
                    <?php foreach (['Balintawak', 'Bugtong', 'Buclanin', 'Dagatan', 'Inosluban', 'Lumbang', 'Marawoy', 'Plaridel', 'Pusil', 'San Lucas', 'Talisay'] as $bgy): ?>
                    <option value="<?= $bgy ?>"><?= $bgy ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Required.</div>
                </div>
                <div class="apm-field">
                  <label for="apmCity">City / Municipality<span class="req">*</span></label>
                  <input type="text" class="form-control" id="apmCity" placeholder="Enter city / municipality" required>
                  <div class="invalid-feedback">Required.</div>
                </div>
              </div>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Consent &amp; Data Sharing</h6>
              <div class="apm-consent-box">
                <label class="form-label fw-semibold mb-1" style="font-size:13px;">Share My Information<span class="req">*</span></label>
                <p class="apm-consent-help">Do you consent to sharing your personal information and medical data with authorized healthcare personnel for your treatment and care?</p>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="apmConsent" id="apmConsentYes" value="yes" required>
                  <label class="form-check-label" for="apmConsentYes" style="font-size:13px;">Yes, I consent to share my information</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="apmConsent" id="apmConsentNo" value="no" required>
                  <label class="form-check-label" for="apmConsentNo" style="font-size:13px;">No, I do not consent to share my information</label>
                </div>
                <div class="apm-consent-note"><i class="bi bi-lock-fill"></i><span>Your information will be kept private and not shared except as required for your care or by law.</span></div>
              </div>
            </div>

          </div>

          <!-- ============ STEP 2: VITAL SIGNS ============ -->
          <div class="apm-step-panel" data-panel="2">
            <div class="apm-section">
              <h6 class="apm-section-title">Vital Signs</h6>
              <div class="apm-grid apm-grid-3">
                <div class="apm-field">
                  <label for="apmVitalDateTime">Date/Time Taken<span class="req">*</span></label>
                  <input type="datetime-local" class="form-control" id="apmVitalDateTime" required>
                  <div class="invalid-feedback">Please select the date and time taken.</div>
                </div>
                <div class="apm-field">
                  <label>Blood Pressure (mmHg)<span class="req">*</span></label>
                  <div class="apm-bp-row">
                    <input type="number" class="form-control" id="apmVitalBpSys" placeholder="Systolic" required min="1" max="300" inputmode="numeric">
                    <span class="apm-bp-sep">/</span>
                    <input type="number" class="form-control" id="apmVitalBpDia" placeholder="Diastolic" required min="1" max="300" inputmode="numeric">
                  </div>
                  <div class="invalid-feedback">Enter valid systolic/diastolic values.</div>
                </div>
                <div class="apm-field">
                  <label for="apmVitalHr">Heart Rate (bpm)<span class="req">*</span></label>
                  <input type="number" class="form-control" id="apmVitalHr" placeholder="Enter heart rate" required min="30" max="220" inputmode="numeric">
                  <div class="invalid-feedback">Enter a heart rate between 30–220 bpm.</div>
                </div>
              </div>
              <div class="apm-grid apm-grid-3">
                <div class="apm-field">
                  <label for="apmVitalTemp">Temperature (°C)<span class="req">*</span></label>
                  <div class="apm-suffix-wrap">
                    <input type="number" step="0.1" class="form-control" id="apmVitalTemp" placeholder="Enter temperature" required min="30" max="45" inputmode="decimal">
                    <span class="apm-suffix">°C</span>
                  </div>
                  <div class="invalid-feedback">Enter a temperature between 30–45°C.</div>
                </div>
                <div class="apm-field">
                  <label for="apmVitalWeight">Weight (kg)<span class="req">*</span></label>
                  <div class="apm-suffix-wrap">
                    <input type="number" step="0.1" class="form-control" id="apmVitalWeight" placeholder="Enter weight" required min="0.1" inputmode="decimal">
                    <span class="apm-suffix">kg</span>
                  </div>
                  <div class="invalid-feedback">Enter a valid weight.</div>
                </div>
                <div class="apm-field">
                  <label for="apmVitalHeight">Height (cm)<span class="req">*</span></label>
                  <div class="apm-suffix-wrap">
                    <input type="number" step="0.1" class="form-control" id="apmVitalHeight" placeholder="Enter height" required min="1" inputmode="decimal">
                    <span class="apm-suffix">cm</span>
                  </div>
                  <div class="invalid-feedback">Enter a valid height.</div>
                </div>
              </div>
              <div class="apm-grid apm-grid-3">
                <div class="apm-field">
                  <label for="apmVitalBmi">BMI (kg/m&sup2;)</label>
                  <input type="text" class="form-control" id="apmVitalBmi" placeholder="Auto-calculated" readonly>
                  <div class="apm-field-help">Calculated automatically based on weight and height.</div>
                </div>
                <div class="apm-field">
                  <label for="apmBloodType">Blood Type <span class="text-muted fw-normal">(Optional)</span></label>
                  <select class="form-select" id="apmBloodType">
                    <option value="" selected>Not specified</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- ============ STEP 3: HISTORY & ALLERGIES ============ -->
          <div class="apm-step-panel" data-panel="3">

            <div class="apm-section">
              <h6 class="apm-section-title">Medical History</h6>
              <div class="apm-table-head apm-hist-row">
                <span>#</span><span>Medical Condition</span><span>Details / Others</span><span></span>
              </div>
              <div id="apmConditionList"></div>
              <button type="button" class="apm-add-row-btn" id="apmAddCondition"><i class="bi bi-plus-lg"></i> Add Medical History</button>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Surgical History</h6>
              <div class="apm-table-head apm-hist-row">
                <span>#</span><span>Surgical Procedure</span><span>Details / Others</span><span></span>
              </div>
              <div id="apmSurgeryList"></div>
              <button type="button" class="apm-add-row-btn" id="apmAddSurgery"><i class="bi bi-plus-lg"></i> Add Surgical History</button>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Allergies</h6>
              <div class="apm-table-head apm-allergy-row">
                <span>#</span><span>Allergen</span><span>Severity</span><span>Reaction / Details</span><span></span>
              </div>
              <div id="apmAllergyList"></div>
              <button type="button" class="apm-add-row-btn" id="apmAddAllergy"><i class="bi bi-plus-lg"></i> Add Allergy</button>
            </div>

          </div>

          <!-- ============ STEP 4: MEDICATIONS ============ -->
          <div class="apm-step-panel" data-panel="4">
            <div class="apm-section">
              <h6 class="apm-section-title">Current Medications <span class="text-muted fw-normal">(Optional)</span></h6>
              <p class="apm-section-subtitle">Add all current medications the patient is taking, if any.</p>
              <div class="apm-table-head apm-med-row">
                <span>#</span><span>Medication Name</span><span>Dosage</span><span>Frequency</span><span>Start Date</span><span>Prescribed By</span><span></span>
              </div>
              <div id="apmMedicationList"></div>
              <button type="button" class="apm-add-row-btn apm-add-row-btn-block" id="apmAddMedication"><i class="bi bi-plus-lg"></i> Add Medication</button>
            </div>

            <div class="apm-section">
              <h6 class="apm-section-title">Medication Notes <span class="text-muted fw-normal">(Optional)</span></h6>
              <p class="apm-section-subtitle">Add any additional notes related to the patient's medications.</p>
              <textarea class="form-control" id="apmMedicationNotes" rows="4" maxlength="500" placeholder="Enter notes (optional)"></textarea>
              <div class="apm-char-counter"><span id="apmMedicationNotesCount">0</span> / 500</div>
            </div>
          </div>

          <!-- ============ STEP 5: REASON FOR VISIT ============ -->
          <div class="apm-step-panel" data-panel="5">
            <div class="apm-section">
              <h6 class="apm-section-title">Reason for Visit</h6>
              <p class="apm-section-subtitle">Provide the primary reason the patient is seeking medical care.</p>

              <div class="apm-field mb-3">
                <label for="apmReasonForVisit">Chief Complaint / Primary Reason for Visit<span class="req">*</span></label>
                <textarea class="form-control" id="apmReasonForVisit" rows="4" maxlength="500" placeholder="Enter chief complaint or primary reason for visit" required></textarea>
                <div class="invalid-feedback">Required.</div>
                <div class="apm-char-counter"><span id="apmReasonForVisitCount">0</span> / 500</div>
              </div>

              <div class="apm-field">
                <label for="apmAssociatedSymptoms">Associated Symptoms <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea class="form-control" id="apmAssociatedSymptoms" rows="4" maxlength="1000" placeholder="Enter associated symptoms (e.g., fever, cough, headache)"></textarea>
                <div class="apm-char-counter"><span id="apmAssociatedSymptomsCount">0</span> / 1000</div>
              </div>
            </div>
          </div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="apm-btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="apm-btn-back d-none" id="apmBackBtn">Previous</button>
        <button type="button" class="apm-btn-next" id="apmNextBtn">Next <i class="bi bi-arrow-right"></i></button>
        <button type="button" class="apm-btn-submit d-none" id="apmSubmitBtn"><i class="bi bi-check-lg"></i> Add Patient</button>
      </div>
    </div>
  </div>
</div>

<!-- Row templates for repeatable History / Allergies / Medications tables — cloned by assets/js/add-patient.js. Each field row carries its own .invalid-feedback so a row that's partially filled in can point at exactly which cell is missing. -->
<template id="apmConditionRowTemplate">
  <div class="apm-repeat-row apm-hist-row" data-row="condition">
    <span class="apm-row-num">1</span>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter medical condition" data-field="label">
      <div class="invalid-feedback">Required if details are filled in.</div>
    </div>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter details" data-field="details">
    </div>
    <button type="button" class="apm-row-remove" aria-label="Remove row"><i class="bi bi-trash"></i></button>
  </div>
</template>

<template id="apmSurgeryRowTemplate">
  <div class="apm-repeat-row apm-hist-row" data-row="surgery">
    <span class="apm-row-num">1</span>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter surgical procedure" data-field="procedure">
      <div class="invalid-feedback">Required if details are filled in.</div>
    </div>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter details" data-field="details">
    </div>
    <button type="button" class="apm-row-remove" aria-label="Remove row"><i class="bi bi-trash"></i></button>
  </div>
</template>

<template id="apmAllergyRowTemplate">
  <div class="apm-repeat-row apm-allergy-row" data-row="allergy">
    <span class="apm-row-num">1</span>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter allergen" data-field="allergen">
      <div class="invalid-feedback">Required.</div>
    </div>
    <div class="apm-cell">
      <select class="form-select" data-field="severity">
        <option value="" selected>Select…</option>
        <option value="mild">Mild</option>
        <option value="moderate">Moderate</option>
        <option value="severe">Severe</option>
      </select>
      <div class="invalid-feedback">Required.</div>
    </div>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Enter reaction / details" data-field="reaction">
      <div class="invalid-feedback">Required.</div>
    </div>
    <button type="button" class="apm-row-remove" aria-label="Remove row"><i class="bi bi-trash"></i></button>
  </div>
</template>

<template id="apmMedicationRowTemplate">
  <div class="apm-repeat-row apm-med-row" data-row="medication">
    <span class="apm-row-num">1</span>
    <div class="apm-cell">
      <input type="text" class="form-control" list="apmMedicineOptions" placeholder="Enter or select medication" data-field="name">
    </div>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="e.g. 10 mg" data-field="dosage">
    </div>
    <div class="apm-cell">
      <select class="form-select" data-field="frequency">
        <option value="" selected>Select…</option>
        <option value="Once daily">Once daily</option>
        <option value="Twice daily">Twice daily</option>
        <option value="Three times daily">Three times daily</option>
        <option value="Four times daily">Four times daily</option>
        <option value="As needed">As needed</option>
        <option value="Other">Other</option>
      </select>
    </div>
    <div class="apm-cell">
      <input type="date" class="form-control" data-field="startDate" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="apm-cell">
      <input type="text" class="form-control" placeholder="Prescribed by" data-field="prescribedBy">
    </div>
    <button type="button" class="apm-row-remove" aria-label="Remove row"><i class="bi bi-trash"></i></button>
  </div>
</template>
