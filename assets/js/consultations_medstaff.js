/* =============================================================
   CONSULTATIONS (Medical Staff) JS (medical-staff/consultations.php)
   ---------------------------------------------------------------
   Drives the "Set Consultation" / "Edit" shared modal (including the
   custom patient-card dropdown — see the modal's HTML comment for why
   it isn't a native <select>), the "View" details modal (populated
   per-row from data-* attributes), and the one-click "Complete" row
   action. All three talk to the real "consultations" Firestore
   collection via api/create-consultation.php and
   api/update-consultation.php — see includes/data/consultations.php's
   header comment. On success the page reloads so the table/stat cards
   re-render from fresh Firestore data instead of patching the DOM
   with a guess at the new state.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;

    const formModalEl = document.getElementById('consultationFormModal');
    const formModal = new bootstrap.Modal(formModalEl);
    const form = document.getElementById('consultationForm');
    const formError = document.getElementById('consultationFormError');
    const submitBtn = document.getElementById('cnsFormSubmitBtn');
    const submitLabel = document.getElementById('cnsFormSubmitLabel');
    const submitBtnDefaultHtml = submitBtn.innerHTML;

    const typeSelect = document.getElementById('cnsFormType');
    const dateTimeInput = document.getElementById('cnsFormDateTime');
    const dateTimeMinIso = dateTimeInput.min; // set server-side to "now" (see consultations.php)
    const personnelInput = document.getElementById('cnsFormPersonnel');
    const complaintInput = document.getElementById('cnsFormComplaint');
    const prioritySelect = document.getElementById('cnsFormPriority');
    const notesInput = document.getElementById('cnsFormNotes');
    const formIdInput = document.getElementById('cnsFormId');

    const vitalTempInput = document.getElementById('cnsFormVitalTemp');
    const vitalBpSysInput = document.getElementById('cnsFormVitalBpSys');
    const vitalBpDiaInput = document.getElementById('cnsFormVitalBpDia');
    const vitalHrInput = document.getElementById('cnsFormVitalHr');
    const vitalWeightInput = document.getElementById('cnsFormVitalWeight');
    const vitalHeightInput = document.getElementById('cnsFormVitalHeight');
    const vitalBmiInput = document.getElementById('cnsFormVitalBmi');

    // Weight + Height -> BMI auto-calc, same formula as the "Add Patient"
    // modal's own Vital Signs step (assets/js/add-patient.js).
    function recalcVitalBmi() {
        const weight = parseFloat(vitalWeightInput.value);
        const heightCm = parseFloat(vitalHeightInput.value);
        if (!weight || !heightCm || weight <= 0 || heightCm <= 0) {
            vitalBmiInput.value = '';
            return;
        }
        const heightM = heightCm / 100;
        vitalBmiInput.value = (weight / (heightM * heightM)).toFixed(1);
    }
    vitalWeightInput.addEventListener('input', recalcVitalBmi);
    vitalHeightInput.addEventListener('input', recalcVitalBmi);

    const patientCardBtn = document.getElementById('cnsPatientCardBtn');
    const patientCardName = document.getElementById('cnsPatientCardName');
    const patientCardMeta = document.getElementById('cnsPatientCardMeta');
    const patientIdInput = document.getElementById('cnsFormPatientId');
    const patientNameInput = document.getElementById('cnsFormPatientName');
    const patientCardError = document.getElementById('cnsPatientCardError');
    const patientMenu = document.getElementById('cnsPatientMenu');
    const patientSearchInput = document.getElementById('cnsPatientSearchInput');
    const patientNoResults = patientMenu.querySelector('.cns-patient-no-results');

    const viewModalEl = document.getElementById('consultationDetailsModal');
    const viewModal = new bootstrap.Modal(viewModalEl);

    // -----------------------------------------------------------
    // Patient card dropdown — a search box up top filters the list
    // below by name (fast for a long roster, no scrolling required to
    // find someone). Patient ID isn't shown anywhere in this picker,
    // only tracked internally via the hidden #cnsFormPatientId input.
    // -----------------------------------------------------------
    function setPatientCard(id, name, gender, age, birthDate, registeredToday, intakeVitals) {
        patientIdInput.value = id;
        patientNameInput.value = name;
        patientCardName.textContent = name || 'Select a patient…';
        const metaParts = [];
        if (gender) metaParts.push(gender);
        if (age) metaParts.push(age + ' years old' + (birthDate ? ' (' + birthDate + ')' : ''));
        patientCardMeta.textContent = metaParts.join(' • ');

        // A patient registered TODAY already had Vital Signs taken at
        // intake (Add Patient modal's Step 2, minutes ago at most) — no
        // need to make them sit through the exact same readings again,
        // so those carry straight over here, still editable. Anyone
        // registered on an earlier day gets a clean slate instead: their
        // last reading could be days/weeks stale, so it's never reused
        // — this also clears out a previous same-day patient's carried-
        // over values when the staff member switches their pick.
        if (registeredToday && intakeVitals) {
            vitalTempInput.value = intakeVitals.temp || '';
            vitalBpSysInput.value = intakeVitals.bpSys || '';
            vitalBpDiaInput.value = intakeVitals.bpDia || '';
            vitalHrInput.value = intakeVitals.hr || '';
            vitalWeightInput.value = intakeVitals.weight || '';
            vitalHeightInput.value = intakeVitals.height || '';
        } else {
            vitalTempInput.value = '';
            vitalBpSysInput.value = '';
            vitalBpDiaInput.value = '';
            vitalHrInput.value = '';
            vitalWeightInput.value = '';
            vitalHeightInput.value = '';
        }
        recalcVitalBmi();

        // RA 9994 (Expanded Senior Citizens Act) priority lane — a
        // patient 60 or older is automatically flagged "Senior" the
        // same way Pregnant/PWD are their own distinct priority values
        // (hc_consultation_priority_meta()), not left to staff to
        // remember to set by hand. Still just a normal <select> value
        // afterward, so staff can change it if a specific case calls
        // for a different priority. Only applied for a NEW patient pick
        // — the Edit row-click handler sets prioritySelect.value from
        // this consultation's own already-saved priority right after
        // calling setPatientCard(), which correctly overrides this.
        const ageNum = parseInt(age, 10);
        if (!isNaN(ageNum) && ageNum >= 60) {
            prioritySelect.value = 'senior';
        } else if (prioritySelect.value === 'senior') {
            // Switching from a previously-picked senior to a different,
            // younger patient — clear the stale auto-set value instead
            // of leaving an inapplicable "Senior" priority behind.
            prioritySelect.value = '';
        }

        patientCardBtn.classList.remove('is-invalid');
        patientCardError.style.display = 'none';
    }

    function filterPatientOptions() {
        const term = patientSearchInput.value.trim().toLowerCase();
        let anyVisible = false;
        patientMenu.querySelectorAll('.cns-patient-option').forEach(function (opt) {
            const match = opt.dataset.name.toLowerCase().indexOf(term) !== -1;
            opt.closest('li').classList.toggle('d-none', !match);
            if (match) anyVisible = true;
        });
        if (patientNoResults) patientNoResults.classList.toggle('d-none', anyVisible);
    }
    patientSearchInput.addEventListener('input', filterPatientOptions);
    patientSearchInput.addEventListener('click', function (e) { e.stopPropagation(); });

    // Focus + reset the search box every time the dropdown opens.
    patientCardBtn.closest('.dropdown').addEventListener('shown.bs.dropdown', function () {
        patientSearchInput.value = '';
        filterPatientOptions();
        patientSearchInput.focus();
    });

    patientMenu.addEventListener('click', function (e) {
        const opt = e.target.closest('.cns-patient-option');
        if (!opt) return;
        setPatientCard(opt.dataset.id, opt.dataset.name, opt.dataset.gender, opt.dataset.age, opt.dataset.birthdate, opt.dataset.registeredToday === '1', {
            temp: opt.dataset.intakeTemp,
            bpSys: opt.dataset.intakeBpsys,
            bpDia: opt.dataset.intakeBpdia,
            hr: opt.dataset.intakeHr,
            weight: opt.dataset.intakeWeight,
            height: opt.dataset.intakeHeight,
        });
    });

    function validatePatientCard() {
        const valid = patientIdInput.value !== '';
        patientCardBtn.classList.toggle('is-invalid', !valid);
        patientCardError.style.display = valid ? 'none' : 'block';
        return valid;
    }

    // -----------------------------------------------------------
    // Open / reset
    // -----------------------------------------------------------
    function resetForm(mode) {
        form.reset();
        form.classList.remove('was-validated');
        formIdInput.value = '';
        dateTimeInput.min = dateTimeMinIso;
        setPatientCard('', '', '', '', '');
        formError.classList.add('d-none');
        document.getElementById('consultationFormModalLabel').textContent = mode === 'edit' ? 'Edit Consultation' : 'Set Consultation';
        document.getElementById('consultationFormModalSubtitle').textContent = mode === 'edit'
            ? 'Update this consultation record.'
            : 'Create a new consultation record for the patient.';
        submitLabel.textContent = mode === 'edit' ? 'Save Changes' : 'Set Consultation';
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtnDefaultHtml;
    }

    // "Set Consultation" toolbar button — blank form, personnel
    // defaults to the signed-in staff member (still editable).
    document.getElementById('btnSetConsultation').addEventListener('click', function () {
        resetForm('create');
        personnelInput.value = window.CNS_STAFF_NAME || '';
        formModal.show();
    });

    // Per-row Edit/View/Complete — reads that row's real data-* attributes.
    document.getElementById('cnsTableBody').addEventListener('click', async function (e) {
        const row = e.target.closest('tr[data-id]');
        if (!row) return;

        if (e.target.closest('.adm-action-btn.edit')) {
            resetForm('edit');
            formIdInput.value = row.dataset.id;
            setPatientCard(row.dataset.patientId, row.dataset.patientName, row.dataset.patientGender, row.dataset.patientAge, row.dataset.patientBirthdate);
            typeSelect.value = row.dataset.type;
            const existingDateTime = row.dataset.datetime ? row.dataset.datetime.slice(0, 16) : '';
            // Already-past consultations (a real record can be edited well
            // after its own dateTime — e.g. fixing a typo in the complaint)
            // must stay editable without the picker rejecting their own
            // unchanged value.
            dateTimeInput.min = existingDateTime && existingDateTime < dateTimeMinIso ? existingDateTime : dateTimeMinIso;
            dateTimeInput.value = existingDateTime;
            personnelInput.value = row.dataset.personnel;
            complaintInput.value = row.dataset.complaint;
            prioritySelect.value = row.dataset.priority;
            notesInput.value = row.dataset.notes || '';
            vitalTempInput.value = row.dataset.vitalTemp || '';
            vitalBpSysInput.value = row.dataset.vitalBpsys || '';
            vitalBpDiaInput.value = row.dataset.vitalBpdia || '';
            vitalHrInput.value = row.dataset.vitalHr || '';
            vitalWeightInput.value = row.dataset.vitalWeight || '';
            vitalHeightInput.value = row.dataset.vitalHeight || '';
            recalcVitalBmi();
            formModal.show();
            return;
        }

        if (e.target.closest('.adm-action-btn.view')) {
            const typeMeta = window.CNS_TYPE_META[row.dataset.type] || window.CNS_TYPE_META.walk_in;
            const statusMeta = window.CNS_STATUS_META[row.dataset.status] || window.CNS_STATUS_META.pending;
            const priorityMeta = window.CNS_PRIORITY_META[row.dataset.priority] || { label: row.dataset.priority || '—', bg: '#EEF0F3', color: '#6B7280' };
            document.getElementById('cnsViewPatient').textContent = row.dataset.patientName;
            document.getElementById('cnsViewType').textContent = typeMeta.label;
            document.getElementById('cnsViewPriority').textContent = priorityMeta.label;
            document.getElementById('cnsViewDateTime').textContent = row.dataset.datetime
                ? new Date(row.dataset.datetime).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
                : '—';
            document.getElementById('cnsViewFollowUp').textContent = row.dataset.followup
                ? new Date(row.dataset.followup).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
                : 'Not scheduled';
            document.getElementById('cnsViewPersonnel').textContent = row.dataset.personnel;
            document.getElementById('cnsViewStatus').textContent = statusMeta.label;
            document.getElementById('cnsViewComplaint').textContent = row.dataset.complaint || '—';
            document.getElementById('cnsViewNotes').textContent = row.dataset.notes || '—';
            viewModal.show();
            return;
        }

        if (e.target.closest('.cns-btn-complete')) {
            const confirmed = await HCDialog.confirm('Mark this consultation as completed?');
            if (!confirmed) return;
            fetch('../api/update-consultation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: row.dataset.id, status: 'completed' }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) { HCDialog.alert(err.message); });
        }
    });

    // Shared Set/Edit form submit.
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const patientOk = validatePatientCard();
        if (!form.checkValidity() || !patientOk) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            patientId: patientIdInput.value,
            patientName: patientNameInput.value,
            consultationType: typeSelect.value,
            dateTime: dateTimeInput.value,
            assignedPersonnel: personnelInput.value.trim(),
            chiefComplaint: complaintInput.value.trim(),
            priority: prioritySelect.value,
            // No nextFollowUp sent — the form has no such field, and
            // api/update-consultation.php only touches it when the key is
            // present, so a doctor-set follow-up date survives staff edits.
            notes: notesInput.value.trim(),
            vitals: {
                temperature: vitalTempInput.value.trim(),
                bloodPressureSystolic: vitalBpSysInput.value.trim(),
                bloodPressureDiastolic: vitalBpDiaInput.value.trim(),
                heartRate: vitalHrInput.value.trim(),
                weight: vitalWeightInput.value.trim(),
                height: vitalHeightInput.value.trim(),
                bmi: vitalBmiInput.value.trim(),
            },
        };

        const isEdit = formIdInput.value !== '';
        const url = isEdit ? '../api/update-consultation.php' : '../api/create-consultation.php';
        if (isEdit) payload.id = formIdInput.value;

        formError.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                formError.textContent = err.message;
                formError.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
