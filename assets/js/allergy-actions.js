/* =====================================================================
   Allergy actions — medical-staff/allergies.php
   ---------------------------------------------------------------------
   - "+ Add Allergy" (#btnAddAllergy) opens
     includes/medical-staff/add_allergy_modal.php; "Save Allergy" POSTs
     to api/create-allergy-record.php.
   - "Mark Inactive" ([data-allergy-action="deactivate"], Active
     Allergies table) and "Reactivate" ([data-allergy-action="reactivate"],
     Inactive / Resolved table) confirm with HCDialog.confirm()
     (assets/js/hc-dialogs.js), then POST to api/update-allergy-status.php.
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
    // Mark Inactive / Reactivate
    // -------------------------------------------------------------
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-allergy-action]');
        if (!btn || btn.disabled) return;
        e.preventDefault();

        const action = btn.dataset.allergyAction;
        const allergen = btn.dataset.allergen || 'this allergy';
        const deactivate = action === 'deactivate';

        if (typeof HCDialog === 'undefined') return;
        const confirmed = await HCDialog.confirm(
            deactivate
                ? '"' + allergen + '" will be moved from Active Allergies to Inactive / Resolved Allergies. The record is kept, not deleted, and can be reactivated later.'
                : '"' + allergen + '" will be moved from Inactive / Resolved Allergies back to Active Allergies. The original record and its history are kept.',
            {
                title: deactivate ? 'Mark Allergy as Inactive?' : 'Reactivate Allergy?',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
            }
        );
        if (!confirmed) return;

        btn.disabled = true;
        try {
            const data = await postJson('../api/update-allergy-status.php', { allergyId: btn.dataset.allergyId, action: action });
            reloadWithFlash(data.message || 'Allergy status updated.');
        } catch (err) {
            btn.disabled = false;
            HCDialog.alert(err.message, { title: 'Could Not Update Allergy' });
        }
    });

    showFlash();
})();
