/* =============================================================
   MEDICAL HISTORY TAB JS (subadmin/doctor/medical-staff's
   medical_history.php)
   ---------------------------------------------------------------
   Wires all three sections (Chronic Conditions / Past Surgeries /
   Family History): each "+ Add" button opens that section's shared
   Add/Edit modal (includes/shared/medical_history_modals.php) blank;
   each row's pencil button opens the same modal pre-filled from its
   data-row="<json>" attribute and sets the hidden id field; each row's
   trash button confirms (HCDialog) then POSTs to the matching
   api/delete-*.php. All three forms POST to api/add-*.php when the
   hidden id is empty, api/update-*.php otherwise, then reload the page
   on success so the table re-renders from fresh Firestore data — same
   confirm+fetch+reload idiom as assets/js/subadmin-actions.js.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;

    /** Generic wiring for one section's Add/Edit modal + form. */
    function wireSection(opts) {
        const modalEl = document.getElementById(opts.modalId);
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById(opts.formId);
        const errorBox = document.getElementById(opts.errorId);
        const submitBtn = document.getElementById(opts.submitBtnId);
        const submitDefaultHtml = submitBtn ? submitBtn.innerHTML : '';
        const titleEl = document.getElementById(opts.modalId + 'Label');
        const idField = document.getElementById(opts.idFieldId);

        function resetForm() {
            form.reset();
            form.classList.remove('was-validated');
            idField.value = '';
            if (errorBox) errorBox.classList.add('d-none');
            opts.fields.forEach(function (f) {
                const el = document.getElementById(f.elId);
                if (el) el.value = '';
            });
        }

        function fillForm(row) {
            idField.value = row.id || '';
            opts.fields.forEach(function (f) {
                const el = document.getElementById(f.elId);
                if (el) el.value = row[f.rowKey] || '';
            });
        }

        document.querySelectorAll('[data-mhx-add="' + opts.key + '"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                resetForm();
                if (titleEl) titleEl.textContent = opts.addTitle;
                modal.show();
            });
        });

        document.querySelectorAll('[data-mhx-edit="' + opts.key + '"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                let row = {};
                try { row = JSON.parse(btn.dataset.row || '{}'); } catch (e) { row = {}; }
                row.id = btn.dataset.id || row.id;
                resetForm();
                fillForm(row);
                if (titleEl) titleEl.textContent = opts.editTitle;
                modal.show();
            });
        });

        document.querySelectorAll('[data-mhx-delete="' + opts.key + '"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.dataset.id;
                const label = btn.dataset.label || 'this entry';
                HCDialog.confirm('Delete ' + label + '? This cannot be undone.').then(function (confirmed) {
                    if (!confirmed) return;

                    fetch('../api/' + opts.deleteEndpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id }),
                    })
                        .then(function (res) {
                            return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                        })
                        .then(function (result) {
                            if (!result.ok || !result.data.success) {
                                throw new Error(result.data.error || 'Something went wrong. Please try again.');
                            }
                            window.location.reload();
                        })
                        .catch(function (err) {
                            HCDialog.alert(err.message);
                        });
                });
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const payload = { id: idField.value, patientId: document.getElementById(opts.patientIdFieldId).value };
            opts.fields.forEach(function (f) {
                const el = document.getElementById(f.elId);
                payload[f.payloadKey] = el ? el.value.trim() : '';
            });

            const endpoint = idField.value ? opts.updateEndpoint : opts.addEndpoint;

            if (errorBox) errorBox.classList.add('d-none');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';
            }

            fetch('../api/' + endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) {
                    return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    if (errorBox) {
                        errorBox.textContent = err.message;
                        errorBox.classList.remove('d-none');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitDefaultHtml;
                    }
                });
        });
    }

    wireSection({
        key: 'condition',
        modalId: 'mhxConditionModal',
        formId: 'mhxConditionForm',
        errorId: 'mhxConditionError',
        submitBtnId: 'mhxConditionSubmitBtn',
        idFieldId: 'mhxConditionId',
        patientIdFieldId: 'mhxConditionPatientId',
        addTitle: 'Add Chronic Condition',
        editTitle: 'Edit Chronic Condition',
        addEndpoint: 'add-condition.php',
        updateEndpoint: 'update-condition.php',
        deleteEndpoint: 'delete-condition.php',
        // payloadKey must match what api/add-condition.php/api/update-condition.php
        // expect in the POST body; rowKey is the key hc_get_medical_conditions_for_patient()
        // returns for pre-filling (its raw-ISO 'diagnosedOnRaw', not the
        // human-formatted 'diagnosedOn' the row displays).
        fields: [
            { payloadKey: 'label', rowKey: 'label', elId: 'mhxConditionLabel' },
            { payloadKey: 'diagnosedOn', rowKey: 'diagnosedOnRaw', elId: 'mhxConditionDiagnosedOn' },
            { payloadKey: 'status', rowKey: 'status', elId: 'mhxConditionStatus' },
            { payloadKey: 'details', rowKey: 'details', elId: 'mhxConditionDetails' },
        ],
    });

    wireSection({
        key: 'surgery',
        modalId: 'mhxSurgeryModal',
        formId: 'mhxSurgeryForm',
        errorId: 'mhxSurgeryError',
        submitBtnId: 'mhxSurgerySubmitBtn',
        idFieldId: 'mhxSurgeryId',
        patientIdFieldId: 'mhxSurgeryPatientId',
        addTitle: 'Add Surgical History',
        editTitle: 'Edit Surgical History',
        addEndpoint: 'add-surgery.php',
        updateEndpoint: 'update-surgery.php',
        deleteEndpoint: 'delete-surgery.php',
        // Same rowKey-vs-payloadKey distinction as the condition section
        // above — hc_get_surgical_history_for_patient() exposes
        // 'datePerformedRaw' (never falls back to dateRecorded, see that
        // function's own comment) for honest pre-fill.
        fields: [
            { payloadKey: 'procedure', rowKey: 'procedure', elId: 'mhxSurgeryProcedure' },
            { payloadKey: 'datePerformed', rowKey: 'datePerformedRaw', elId: 'mhxSurgeryDate' },
            { payloadKey: 'surgeonFacility', rowKey: 'surgeonFacility', elId: 'mhxSurgeryFacility' },
            { payloadKey: 'details', rowKey: 'details', elId: 'mhxSurgeryDetails' },
        ],
    });

    wireSection({
        key: 'family',
        modalId: 'mhxFamilyModal',
        formId: 'mhxFamilyForm',
        errorId: 'mhxFamilyError',
        submitBtnId: 'mhxFamilySubmitBtn',
        idFieldId: 'mhxFamilyId',
        patientIdFieldId: 'mhxFamilyPatientId',
        addTitle: 'Add Family History',
        editTitle: 'Edit Family History',
        addEndpoint: 'add-family-history.php',
        updateEndpoint: 'update-family-history.php',
        deleteEndpoint: 'delete-family-history.php',
        fields: [
            { payloadKey: 'condition', rowKey: 'condition', elId: 'mhxFamilyCondition' },
            { payloadKey: 'relation', rowKey: 'relation', elId: 'mhxFamilyRelation' },
        ],
    });
});
