/* =====================================================================
   Add Vaccination Record — medical-staff/vaccination_records.php
   ---------------------------------------------------------------------
   "+ Add Vaccination" (#btnAddVaccination) opens
   includes/medical-staff/add_vaccination_modal.php; "Save Vaccination"
   POSTs to api/add-vaccination-record.php and reloads the page so the
   new record appears in the Vaccination History table.

   Record Type drives the rest of the form:
     - Historical / Administered outside the facility: nothing
       auto-filled — Administered By and Administering Facility may
       stay blank when unknown; the date may be anywhere from the
       patient's birth date to today. These two differ only in the
       stored recordType value (before-registration paper record vs.
       a vaccine given at another provider), not in form behavior.
     - Administered here: Administering Facility is locked to the staff
       member's facility, Administered By is pre-filled with their name
       (editable, required), and the date can't predate registration.

   Everything is delegated from `document` and bound only once (the
   window flag below), resolving elements at event time: this page is
   a Patient Profile tab, so assets/js/ajax-tab-nav.js swaps its <main>
   in place and re-injects this script on every visit — per-element
   listeners would be lost on swap, and unguarded document listeners
   would stack up and submit the form more than once.
   ===================================================================== */
(function () {
    'use strict';

    if (window.hcAddVaccinationBound) return;
    window.hcAddVaccinationBound = true;

    const el = function (id) { return document.getElementById(id); };
    const val = function (id) { return el(id).value.trim(); };

    // Vaccine name -> type, so picking/typing a known name pre-fills
    // Vaccine Type (only while the user hasn't typed/picked one
    // themselves). Vaccine Type is free text now (see below), so
    // these fill in the exact label text from
    // includes/firestore/vaccination_records.php's hc_vaccine_type_meta()
    // rather than its internal key — keep the two in sync.
    // Order matters: Td and Pentavalent before plain Tetanus/Hep B.
    const TYPE_BY_NAME = [
        [/pentavalent|dpt-hepb|hib/i, 'Pentavalent (DPT-HepB-Hib)'],
        [/tetanus[- ]diphtheria|\btd\b/i, 'Tetanus-Diphtheria (Td)'],
        [/tetanus|\btt\b/i, 'Tetanus'],
        [/covid|pfizer|moderna|sinovac|coronavac|astrazeneca|janssen/i, 'COVID-19'],
        [/influenza|\bflu\b/i, 'Influenza'],
        [/hepatitis b|hep ?b/i, 'Hepatitis B'],
        [/polio|\bopv\b|\bipv\b/i, 'Polio (OPV/IPV)'],
        [/pneumo|\bpcv|\bppsv/i, 'Pneumococcal'],
        [/measles|rubella|\bmmr\b|\bmr\b/i, 'Measles (MR/MMR)'],
        [/\bbcg\b/i, 'BCG'],
        [/\bhpv\b|papilloma/i, 'HPV'],
        [/rabies/i, 'Rabies'],
    ];

    function modalData() { return el('addVaccinationModal').dataset; }

    function recordType() {
        const checked = document.querySelector('input[name="avxRecordType"]:checked');
        return checked ? checked.value : '';
    }

    function showError(message) {
        const errorBox = el('addVaccinationError');
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    // Earliest allowed Date Administered for the current record type.
    function minDate() {
        const d = modalData();
        if (recordType() === 'administered_here' && d.registeredOn) return d.registeredOn;
        return d.birthDate || '';
    }

    function syncDateBounds() {
        el('avxDateAdministered').min = minDate();
        el('avxNextDoseDue').min = el('avxDateAdministered').value || '';
    }

    function applyRecordType() {
        const d = modalData();
        const here = recordType() === 'administered_here';
        const givenBy = el('avxGivenBy');
        const facility = el('avxFacility');

        if (here) {
            facility.value = d.staffFacility;
            facility.readOnly = true;
            if (givenBy.value.trim() === '') givenBy.value = d.staffName;
            givenBy.required = true;
            el('avxGivenByHint').textContent = '';
            el('avxFacilityHint').textContent = '';
            el('avxDateHelp').textContent = 'The actual date the vaccine was given (on or after registration).';
        } else {
            // Undo only what "here" auto-filled — never assign the current
            // staff member/facility to a historical or outside-facility vaccine.
            if (facility.readOnly) facility.value = '';
            facility.readOnly = false;
            if (givenBy.value.trim() === d.staffName) givenBy.value = '';
            givenBy.required = false;
            el('avxGivenByHint').textContent = '(If known)';
            el('avxFacilityHint').textContent = '(If known)';
            el('avxDateHelp').textContent = 'The actual date the vaccine was given — from the vaccination card or record, even if before registration.';
        }
        syncDateBounds();
    }

    function resetForm() {
        const form = el('addVaccinationForm');
        form.reset();
        form.classList.remove('was-validated');
        el('avxType').dataset.userPicked = '';
        el('addVaccinationError').classList.add('d-none');
        el('avxFacility').readOnly = false;
        el('avxNextDoseDue').setCustomValidity('');
        el('avxDateAdministered').setCustomValidity('');
        applyRecordType();
    }

    // Custom date rules on top of the min/max attributes, with messages
    // that say which rule failed.
    function validateDates() {
        const d = modalData();
        const date = el('avxDateAdministered');
        const next = el('avxNextDoseDue');
        let dateMsg = '';

        if (date.value) {
            if (date.value > d.today) {
                dateMsg = 'Date administered cannot be in the future.';
            } else if (d.birthDate && date.value < d.birthDate) {
                dateMsg = 'Date administered cannot be before the patient\'s birth date.';
            } else if (recordType() === 'administered_here' && d.registeredOn && date.value < d.registeredOn) {
                dateMsg = 'This date is before the patient registered. Choose "Historical Vaccination" instead.';
            }
        }
        date.setCustomValidity(dateMsg);
        el('avxDateFeedback').textContent = dateMsg || 'Please enter a valid date administered.';

        next.setCustomValidity(next.value && date.value && next.value <= date.value ? 'invalid' : '');
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#btnAddVaccination')) return;
        const modalEl = el('addVaccinationModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        resetForm();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener('change', function (e) {
        const id = e.target.id;
        if (e.target.name === 'avxRecordType') {
            applyRecordType();
            validateDates();
        } else if (id === 'avxDateAdministered' || id === 'avxNextDoseDue') {
            syncDateBounds();
            validateDates();
        } else if (id === 'avxType') {
            e.target.dataset.userPicked = '1';
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.id === 'avxType') {
            // Typing directly into Vaccine Type is itself a pick — stop
            // Vaccine Name's auto-fill from overwriting it, even before
            // the field is blurred (change fires too late for that).
            e.target.dataset.userPicked = '1';
            return;
        }
        if (e.target.id !== 'avxName') return;
        const typeInput = el('avxType');
        if (typeInput.dataset.userPicked) return;
        const match = TYPE_BY_NAME.find(function (pair) { return pair[0].test(e.target.value); });
        if (match) typeInput.value = match[1];
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.id !== 'addVaccinationForm') return;
        e.preventDefault();
        e.stopPropagation();
        el('addVaccinationError').classList.add('d-none');

        validateDates();
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        const payload = {
            patientId: val('avxPatientId'),
            recordType: recordType(),
            name: val('avxName'),
            type: val('avxType'),
            dateAdministered: el('avxDateAdministered').value,
            dose: val('avxDose'),
            nextDoseDue: el('avxNextDoseDue').value,
            givenBy: val('avxGivenBy'),
            facility: val('avxFacility'),
            notes: val('avxNotes'),
        };

        const submitBtn = el('avxSubmitBtn');
        const submitBtnDefaultHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

        fetch('../api/add-vaccination-record.php', {
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
                showError(err.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
})();
