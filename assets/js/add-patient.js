/* =============================================================
   ADD PATIENT MODAL JS (medical-staff/patient_management.php)
   ---------------------------------------------------------------
   Drives the 5-step intake wizard in
   includes/medical-staff/add_patient_modal.php: step navigation +
   per-step (per-field-group, so the Blood Pressure systolic/diastolic
   pair validates as one field) validation, completed steps are
   click-to-revisit, Date of Birth -> Age and Weight+Height -> BMI
   auto-calc, repeatable rows for Known Conditions / Allergies /
   Medications (cloned from <template> elements), then a single POST
   to api/create-patient.php
   on final submit. On success the page reloads so the table/stat
   cards re-render from the fresh Firestore data instead of patching
   the DOM with a guess at what the new row looks like.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addPatientModal');
    const form = document.getElementById('addPatientForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('addPatientError');
    const stepperEl = document.getElementById('apmStepper');
    const backBtn = document.getElementById('apmBackBtn');
    const nextBtn = document.getElementById('apmNextBtn');
    const submitBtn = document.getElementById('apmSubmitBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    const TOTAL_STEPS = 5;
    let currentStep = 1;

    // -----------------------------------------------------------
    // Step navigation
    // -----------------------------------------------------------
    function panelFor(step) { return form.querySelector('.apm-step-panel[data-panel="' + step + '"]'); }

    function goToStep(step) {
        currentStep = step;
        form.querySelectorAll('.apm-step-panel').forEach(function (p) {
            p.classList.toggle('active', Number(p.dataset.panel) === step);
        });
        stepperEl.querySelectorAll('.apm-step').forEach(function (s) {
            const n = Number(s.dataset.step);
            s.classList.toggle('active', n === step);
            s.classList.toggle('done', n < step);
        });
        backBtn.classList.toggle('d-none', step === 1);
        nextBtn.classList.toggle('d-none', step === TOTAL_STEPS);
        submitBtn.classList.toggle('d-none', step !== TOTAL_STEPS);
        modalEl.querySelector('.modal-body').scrollTop = 0;
    }

    // Completed steps are selectable — jump straight back to one by
    // clicking its stepper tab, matching the "neutral but selectable"
    // completed-step behavior. Future (not-yet-reached) steps stay
    // inert since their required fields haven't been validated yet.
    stepperEl.querySelectorAll('.apm-step').forEach(function (s) {
        s.addEventListener('click', function () {
            const n = Number(s.dataset.step);
            if (n < currentStep) goToStep(n);
        });
    });

    // Groups fields by their enclosing .apm-field so multi-input groups
    // (e.g. the Blood Pressure systolic/diastolic pair) validate and
    // show a single shared error message as one unit, not two.
    function validateStep(step) {
        const panel = panelFor(step);
        let valid = true;

        panel.querySelectorAll('.apm-field').forEach(function (fieldEl) {
            const inputs = fieldEl.querySelectorAll('input[required], select[required], textarea[required]');
            if (inputs.length === 0) return;

            let fieldValid = true;
            inputs.forEach(function (inp) {
                if (!inp.checkValidity()) fieldValid = false;
            });
            inputs.forEach(function (inp) { inp.classList.toggle('is-invalid', !fieldValid); });

            const feedback = fieldEl.querySelector('.invalid-feedback');
            if (feedback) feedback.style.display = fieldValid ? 'none' : 'block';
            if (!fieldValid) valid = false;
        });

        // Consent radios live in .apm-consent-box, not .apm-field.
        const consentGroup = panel.querySelectorAll('input[name="apmConsent"]');
        if (consentGroup.length) {
            const checked = Array.from(consentGroup).some(function (r) { return r.checked; });
            consentGroup.forEach(function (r) { r.classList.toggle('is-invalid', !checked); });
            if (!checked) valid = false;
        }

        return valid;
    }

    nextBtn.addEventListener('click', function () {
        if (!validateStep(currentStep)) return;
        if (currentStep === 3) {
            const conditionsOk = validateRepeatTable('apmConditionList', ['label']);
            const surgeriesOk = validateRepeatTable('apmSurgeryList', ['procedure']);
            const allergiesOk = validateRepeatTable('apmAllergyList', ['allergen', 'severity', 'reaction']);
            if (!conditionsOk || !surgeriesOk || !allergiesOk) return;
        }
        // Medications are fully optional — no field within a started row
        // is enforced (unlike Step 3's tables, which still require their
        // primary field once a row has been started).
        if (currentStep < TOTAL_STEPS) goToStep(currentStep + 1);
    });
    backBtn.addEventListener('click', function () {
        if (currentStep > 1) goToStep(currentStep - 1);
    });

    // -----------------------------------------------------------
    // Date of Birth -> Age auto-calc
    // -----------------------------------------------------------
    const birthDateInput = document.getElementById('apmBirthDate');
    const ageInput = document.getElementById('apmAge');
    birthDateInput.addEventListener('change', function () {
        if (!birthDateInput.value) { ageInput.value = ''; return; }
        const dob = new Date(birthDateInput.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        ageInput.value = age >= 0 ? age : '';
    });

    // -----------------------------------------------------------
    // Vital Signs: Weight + Height -> BMI auto-calc
    // -----------------------------------------------------------
    const weightInput = document.getElementById('apmVitalWeight');
    const heightInput = document.getElementById('apmVitalHeight');
    const bmiInput = document.getElementById('apmVitalBmi');

    function recalcBmi() {
        const weight = parseFloat(weightInput.value);
        const heightCm = parseFloat(heightInput.value);
        if (!weight || !heightCm || weight <= 0 || heightCm <= 0) {
            bmiInput.value = '';
            return;
        }
        const heightM = heightCm / 100;
        bmiInput.value = (weight / (heightM * heightM)).toFixed(1);
    }
    weightInput.addEventListener('input', recalcBmi);
    heightInput.addEventListener('input', recalcBmi);

    // -----------------------------------------------------------
    // Character counters (Medication Notes, Chief Complaint, Associated Symptoms)
    // -----------------------------------------------------------
    function wireCharCounter(inputId, countId) {
        const input = document.getElementById(inputId);
        const count = document.getElementById(countId);
        input.addEventListener('input', function () {
            count.textContent = String(input.value.length);
        });
        return { input: input, count: count };
    }
    const reasonForVisitCounter = wireCharCounter('apmReasonForVisit', 'apmReasonForVisitCount');
    const associatedSymptomsCounter = wireCharCounter('apmAssociatedSymptoms', 'apmAssociatedSymptomsCount');

    // Date/Time Taken defaults to right now when the modal opens
    // ("Date defaults to the current consultation date").
    function defaultDateTimeTaken() {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('apmVitalDateTime').value = now.toISOString().slice(0, 16);
    }

    // -----------------------------------------------------------
    // Repeatable rows (Medical History / Surgical History / Allergies /
    // Medications) — row numbers renumber automatically after every
    // add/remove.
    // -----------------------------------------------------------
    function renumberRows(listId) {
        document.getElementById(listId).querySelectorAll('[data-row]').forEach(function (row, i) {
            const numEl = row.querySelector('.apm-row-num');
            if (numEl) numEl.textContent = String(i + 1);
        });
    }

    function addRow(listId, templateId) {
        const list = document.getElementById(listId);
        const tpl = document.getElementById(templateId);
        const node = tpl.content.firstElementChild.cloneNode(true);
        node.querySelector('.apm-row-remove').addEventListener('click', function () {
            node.remove();
            renumberRows(listId);
        });
        // Color-codes the Allergy Severity <select> by its chosen value
        // (mild/moderate/severe) via CSS in add_patient_modal.css.
        const severitySelect = node.querySelector('[data-field="severity"]');
        if (severitySelect) {
            severitySelect.addEventListener('change', function () {
                severitySelect.dataset.severity = severitySelect.value;
            });
        }
        // New medication rows default "Prescribed By" to the staff
        // member entering this intake (still editable — the patient's
        // medication may have actually been prescribed by someone
        // else, e.g. a prior doctor).
        const prescribedByInput = node.querySelector('[data-field="prescribedBy"]');
        if (prescribedByInput && window.APM_STAFF_NAME) {
            prescribedByInput.value = window.APM_STAFF_NAME;
        }
        list.appendChild(node);
        renumberRows(listId);
    }

    function collectRows(listId, fieldNames) {
        const list = document.getElementById(listId);
        const rows = [];
        list.querySelectorAll('[data-row]').forEach(function (row) {
            const entry = {};
            let hasValue = false;
            fieldNames.forEach(function (name) {
                const el = row.querySelector('[data-field="' + name + '"]');
                const val = el ? el.value.trim() : '';
                entry[name] = val;
                if (val !== '') hasValue = true;
            });
            if (hasValue) rows.push(entry);
        });
        return rows;
    }

    // A row only becomes required once the staff member has started
    // filling it in — a fully blank row (an unused extra "+ Add" row)
    // is simply ignored, not an error. requiredFields is checked only
    // for rows where at least one field (required or not) has a value.
    function validateRepeatTable(listId, requiredFields) {
        const list = document.getElementById(listId);
        let valid = true;
        list.querySelectorAll('[data-row]').forEach(function (row) {
            const allFields = row.querySelectorAll('[data-field]');
            const hasAnyValue = Array.from(allFields).some(function (el) { return el.value.trim() !== ''; });

            allFields.forEach(function (el) {
                const isRequiredHere = hasAnyValue && requiredFields.indexOf(el.dataset.field) !== -1;
                const missing = isRequiredHere && el.value.trim() === '';
                el.classList.toggle('is-invalid', missing);
                const feedback = el.closest('.apm-cell') && el.closest('.apm-cell').querySelector('.invalid-feedback');
                if (feedback) feedback.style.display = missing ? 'block' : 'none';
                if (missing) valid = false;
            });
        });
        return valid;
    }

    document.getElementById('apmAddCondition').addEventListener('click', function () {
        addRow('apmConditionList', 'apmConditionRowTemplate');
    });
    document.getElementById('apmAddSurgery').addEventListener('click', function () {
        addRow('apmSurgeryList', 'apmSurgeryRowTemplate');
    });
    document.getElementById('apmAddAllergy').addEventListener('click', function () {
        addRow('apmAllergyList', 'apmAllergyRowTemplate');
    });
    document.getElementById('apmAddMedication').addEventListener('click', function () {
        addRow('apmMedicationList', 'apmMedicationRowTemplate');
    });

    // Medication Notes character counter (max 500, enforced natively
    // via the textarea's maxlength attribute).
    const medNotesInput = document.getElementById('apmMedicationNotes');
    const medNotesCount = document.getElementById('apmMedicationNotesCount');
    medNotesInput.addEventListener('input', function () {
        medNotesCount.textContent = String(medNotesInput.value.length);
    });

    // -----------------------------------------------------------
    // Open / reset
    // -----------------------------------------------------------
    document.querySelectorAll('#btnAddPatient').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.reset();
            form.querySelectorAll('.is-invalid').forEach(function (f) { f.classList.remove('is-invalid'); });
            form.querySelectorAll('.invalid-feedback').forEach(function (f) { f.style.display = ''; });
            ['apmConditionList', 'apmSurgeryList', 'apmAllergyList', 'apmMedicationList'].forEach(function (id) {
                document.getElementById(id).innerHTML = '';
            });
            addRow('apmConditionList', 'apmConditionRowTemplate');
            addRow('apmSurgeryList', 'apmSurgeryRowTemplate');
            addRow('apmAllergyList', 'apmAllergyRowTemplate');
            addRow('apmMedicationList', 'apmMedicationRowTemplate');
            medNotesCount.textContent = '0';
            reasonForVisitCounter.count.textContent = '0';
            associatedSymptomsCounter.count.textContent = '0';
            bmiInput.value = '';
            defaultDateTimeTaken();
            errorBox.classList.add('d-none');
            goToStep(1);
            modal.show();
        });
    });

    // -----------------------------------------------------------
    // Final submit
    // -----------------------------------------------------------
    submitBtn.addEventListener('click', function () {
        if (!validateStep(TOTAL_STEPS)) return;

        const payload = {
            firstName: document.getElementById('apmFirstName').value.trim(),
            middleName: document.getElementById('apmMiddleName').value.trim(),
            lastName: document.getElementById('apmLastName').value.trim(),
            birthDate: document.getElementById('apmBirthDate').value,
            sex: document.getElementById('apmSex').value,
            civilStatus: document.getElementById('apmCivilStatus').value,
            occupation: document.getElementById('apmOccupation').value.trim(),
            phone: document.getElementById('apmPhone').value.trim(),
            address: document.getElementById('apmAddress').value.trim(),
            barangay: document.getElementById('apmBarangay').value,
            city: document.getElementById('apmCity').value.trim(),
            consent: (form.querySelector('input[name="apmConsent"]:checked') || {}).value || '',
            bloodType: document.getElementById('apmBloodType').value,

            vitals: {
                dateTimeTaken: document.getElementById('apmVitalDateTime').value,
                bloodPressureSystolic: document.getElementById('apmVitalBpSys').value.trim(),
                bloodPressureDiastolic: document.getElementById('apmVitalBpDia').value.trim(),
                heartRate: document.getElementById('apmVitalHr').value.trim(),
                temperature: document.getElementById('apmVitalTemp').value.trim(),
                weight: weightInput.value.trim(),
                height: heightInput.value.trim(),
                bmi: bmiInput.value.trim(),
            },

            conditions: collectRows('apmConditionList', ['label', 'details']),
            surgeries: collectRows('apmSurgeryList', ['procedure', 'details']),
            allergies: collectRows('apmAllergyList', ['allergen', 'severity', 'reaction']),
            medications: collectRows('apmMedicationList', ['name', 'dosage', 'frequency', 'startDate', 'prescribedBy']),
            medicationNotes: medNotesInput.value.trim(),

            reasonForVisit: document.getElementById('apmReasonForVisit').value.trim(),
            associatedSymptoms: document.getElementById('apmAssociatedSymptoms').value.trim(),
        };

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding…';

        fetch('../api/create-patient.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
