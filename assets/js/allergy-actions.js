/* =====================================================================
   Allergy actions — doctor/allergies.php and medical-staff/allergies.php
   ---------------------------------------------------------------------
   - "+ Add Allergy" (#btnAddAllergy, medical-staff only) opens
     includes/medical-staff/add_allergy_modal.php; "Save Allergy" POSTs
     to api/create-allergy-record.php.
   - "Manage Allergy" ([data-allergy-manage="manage"], Active Allergies
     table) and "Reactivate Allergy" ([data-allergy-manage="reactivate"],
     Inactive / Resolved table) both open the ONE shared
     includes/shared/manage_allergy_modal.php, pre-filled from the
     clicked row's data-* attributes; "Save Status Change" POSTs to
     api/update-allergy-clinical-status.php (multipart/form-data — the
     optional attachment needs real FormData, not JSON).
   Every success reloads the page — the server re-renders both tables
   and the summary cards from the real records — and a one-shot
   success toast is shown after the reload (sessionStorage).

   Everything is delegated from `document` and bound only once (the
   window flag below): this page is a Patient Profile tab, so
   assets/js/ajax-tab-nav.js swaps its <main> in place and re-injects
   this script on every visit.
   ===================================================================== */
(function () {
    'use strict';

    const FLASH_KEY = 'hcAllergyFlash';

    if (window.hcAllergyActionsBound) {
        showFlash();
        return;
    }
    window.hcAllergyActionsBound = true;

    const el = function (id) { return document.getElementById(id); };
    const val = function (id) { return el(id).value.trim(); };

    // -------------------------------------------------------------
    // Success toast (shown once, after the reload that follows a save)
    // -------------------------------------------------------------
    function reloadWithFlash(message) {
        try { sessionStorage.setItem(FLASH_KEY, message); } catch (e) { /* storage blocked — skip the toast */ }
        window.location.reload();
    }

    function showFlash() {
        let message = null;
        try {
            message = sessionStorage.getItem(FLASH_KEY);
            sessionStorage.removeItem(FLASH_KEY);
        } catch (e) { return; }
        if (!message || typeof bootstrap === 'undefined') return;

        const wrap = document.createElement('div');
        wrap.className = 'toast-container position-fixed bottom-0 end-0 p-3 alg-toast-container';
        wrap.innerHTML =
            '<div class="toast alg-toast" role="status" aria-live="polite" aria-atomic="true">' +
                '<div class="d-flex align-items-center">' +
                    '<i class="bi bi-check-circle-fill alg-toast-icon"></i>' +
                    '<div class="toast-body"></div>' +
                    '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                '</div>' +
            '</div>';
        wrap.querySelector('.toast-body').textContent = message;
        document.body.appendChild(wrap);
        const toastEl = wrap.querySelector('.toast');
        toastEl.addEventListener('hidden.bs.toast', function () { wrap.remove(); });
        bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 }).show();
    }

    function postJson(url, payload) {
        return fetch(url, {
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
                return result.data;
            });
    }

    // Same shape as postJson(), but for requests carrying a file
    // (the Manage Allergy modal's optional attachment) — fetch sets
    // the multipart Content-Type/boundary itself when given a
    // FormData body, so no headers are set here.
    function postFormData(url, formData) {
        return fetch(url, { method: 'POST', body: formData })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                return result.data;
            });
    }

    // -------------------------------------------------------------
    // Add Allergy
    // -------------------------------------------------------------
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#btnAddAllergy')) return;
        const form = el('addAllergyForm');
        const modalEl = el('addAllergyModal');
        if (!form || !modalEl || typeof bootstrap === 'undefined') return;

        form.reset();
        form.classList.remove('was-validated');
        el('addAllergyError').classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener('shown.bs.modal', function (e) {
        if (e.target.id === 'addAllergyModal') el('aalAllergen').focus();
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.id !== 'addAllergyForm') return;
        e.preventDefault();
        e.stopPropagation();

        const errorBox = el('addAllergyError');
        errorBox.classList.add('d-none');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        const payload = {
            patientId: val('aalPatientId'),
            allergen: val('aalAllergen'),
            type: val('aalType'),
            reaction: val('aalReaction'),
            severity: val('aalSeverity'),
            onsetDate: el('aalOnsetDate').value,
            status: val('aalStatus'),
            notes: val('aalNotes'),
        };

        const submitBtn = el('aalSubmitBtn');
        const submitBtnDefaultHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

        postJson('../api/create-allergy-record.php', payload)
            .then(function () {
                reloadWithFlash('"' + payload.allergen + '" was added to the patient\'s allergies.');
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });

    // -------------------------------------------------------------
    // Manage Allergy / Reactivate Allergy — both open the ONE shared
    // modal, pre-filled from the clicked row's data-* attributes.
    // -------------------------------------------------------------
    function setBadge(id, label, bg, color) {
        const node = el(id);
        if (!node) return;
        node.textContent = label || '—';
        node.style.background = bg || '';
        node.style.color = color || '';
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-allergy-manage]');
        if (!btn || btn.disabled) return;
        e.preventDefault();

        const modalEl = el('manageAllergyModal');
        const form = el('manageAllergyForm');
        if (!modalEl || !form || typeof bootstrap === 'undefined') return;

        form.reset();
        form.classList.remove('was-validated');
        el('manageAllergyError').classList.add('d-none');

        const d = btn.dataset;
        el('malAllergyId').value = d.allergyId || '';
        el('malAllergenName').textContent = d.allergen || '';
        el('malTypeLabel').textContent = d.allergyType + (d.allergySubtype ? ' (' + d.allergySubtype + ')' : '');
        el('malReaction').textContent = d.allergyReaction || '—';
        el('malOnsetDate').textContent = d.allergyOnset || '—';

        setBadge('malSeverity', d.allergySeverityLabel, d.allergySeverityBg, d.allergySeverityColor);
        setBadge('malCurrentClinicalStatus', d.clinicalStatusLabel, d.clinicalStatusBg, d.clinicalStatusColor);
        setBadge('malCurrentVerificationStatus', d.verificationStatusLabel, '', '');

        const iconEl = el('malTypeIcon');
        if (iconEl) {
            iconEl.style.background = d.allergyIconBg || '';
            iconEl.style.color = d.allergyIconColor || '';
            const i = iconEl.querySelector('i');
            if (i) i.className = 'bi ' + (d.allergyIcon || 'bi-exclamation-triangle-fill');
        }

        const today = new Date().toISOString().slice(0, 10);
        el('malAssessmentDate').value = today;
        el('malAssessmentDate').max = today;
        if (d.allergyOnsetRaw) el('malAssessmentDate').min = d.allergyOnsetRaw;
        el('malAssessedBy').value = window.HC_CURRENT_USER_NAME || '';

        const isReactivate = d.allergyManage === 'reactivate';
        el('manageAllergyModalLabel').textContent = isReactivate ? 'Reactivate Allergy' : 'Manage Allergy';
        el('malNewClinicalStatus').value = isReactivate ? 'active' : '';

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.id !== 'manageAllergyForm') return;
        e.preventDefault();
        e.stopPropagation();

        const errorBox = el('manageAllergyError');
        errorBox.classList.add('d-none');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid) firstInvalid.focus();
            return;
        }

        const fd = new FormData();
        fd.append('allergyId', val('malAllergyId'));
        fd.append('newClinicalStatus', val('malNewClinicalStatus'));
        fd.append('newVerificationStatus', val('malNewVerificationStatus'));
        fd.append('reason', val('malReason'));
        fd.append('assessmentDate', el('malAssessmentDate').value);
        const fileInput = el('malAttachment');
        if (fileInput.files && fileInput.files[0]) {
            fd.append('attachment', fileInput.files[0]);
        }

        const submitBtn = el('malSubmitBtn');
        const submitBtnDefaultHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

        postFormData('../api/update-allergy-clinical-status.php', fd)
            .then(function (data) {
                reloadWithFlash(data.message || 'Allergy status updated.');
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });

    showFlash();
})();
