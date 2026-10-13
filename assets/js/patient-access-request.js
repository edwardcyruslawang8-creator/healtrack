/* =============================================================
   PATIENT ACCESS REQUEST (doctor/patient_management.php)
   ---------------------------------------------------------------
   Two real, independent flows on this one page — see
   includes/shared/patient_access_requests.php's header comment for
   the full "doctor requests access to another doctor's patient +
   patient email consent" workflow this drives the doctor-facing half
   of:

     1. "Request Access" button on a locked row -> opens
        #requestAccessModal -> POSTs to api/request-patient-access.php.
     2. Inline Approve/Deny buttons in the "Pending Access Requests"
        panel (only rendered for a doctor who owns at least one patient
        a colleague has asked to view) -> POSTs to
        api/decide-patient-access-request.php.

   Both success paths reload the page — same "re-render from fresh
   Firestore data" pattern used everywhere else in this app (e.g.
   assets/js/two-fa.js) — so the row/panel reflect the new real state
   immediately.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (result) {
            if (!result.ok || !result.data.success) {
                throw new Error(result.data.error || 'Something went wrong. Please try again.');
            }
            return result.data;
        });
    }

    /* ---------- Request Access modal ---------- */
    var modalEl = document.getElementById('requestAccessModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        var modal = new bootstrap.Modal(modalEl);
        var patientIdInput = document.getElementById('raPatientId');
        var patientNameEl = document.getElementById('raPatientName');
        var ownerNameEl = document.getElementById('raOwnerName');
        var reasonInput = document.getElementById('raReason');
        var errorBox = document.getElementById('raError');
        var submitBtn = document.getElementById('raSubmitBtn');
        var submitBtnDefaultHtml = submitBtn.innerHTML;

        document.querySelectorAll('.pmg-btn-request-access').forEach(function (btn) {
            btn.addEventListener('click', function () {
                patientIdInput.value = btn.getAttribute('data-patient-id') || '';
                patientNameEl.textContent = btn.getAttribute('data-patient-name') || 'This patient';
                ownerNameEl.textContent = btn.getAttribute('data-owner-name') || 'another doctor';
                reasonInput.value = '';
                errorBox.classList.add('d-none');
                modal.show();
            });
        });

        submitBtn.addEventListener('click', function () {
            errorBox.classList.add('d-none');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

            postJson('../api/request-patient-access.php', {
                patientId: patientIdInput.value,
                reason: reasonInput.value.trim(),
            })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    errorBox.textContent = err.message;
                    errorBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnDefaultHtml;
                });
        });
    }

    /* ---------- Pending Access Requests panel: inline Approve/Deny ---------- */
    document.querySelectorAll('.pmg-access-decide-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var requestId = btn.getAttribute('data-request-id');
            var action = btn.getAttribute('data-action');
            if (!requestId || !action) return;

            if (action === 'deny' && !(await HCDialog.confirm('Deny this access request?'))) {
                return;
            }

            var row = btn.closest('tr');
            var siblingButtons = row ? row.querySelectorAll('.pmg-access-decide-btn') : [btn];
            siblingButtons.forEach(function (b) { b.disabled = true; });

            postJson('../api/decide-patient-access-request.php', { requestId: requestId, action: action })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    siblingButtons.forEach(function (b) { b.disabled = false; });
                });
        });
    });
});
