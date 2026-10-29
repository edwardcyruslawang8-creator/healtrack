/* =============================================================
   PATIENT CONSENT REQUEST FORM JS
   ---------------------------------------------------------------
   Shared by subadmin/medical-staff/doctor's own patient_profile.php
   "Patient Details Restricted" card — submits the reason textarea to
   api/request-patient-consent.php, then reloads so the real
   "awaiting response" state (hc_patient_consent_status()) takes over
   immediately, same pattern every other create-then-reload form in
   this app already uses.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('patientConsentRequestForm');
    if (!form) return;

    const errorBox = document.getElementById('pcrError');
    const submitBtn = document.getElementById('pcrSubmitBtn');
    const reasonInput = document.getElementById('pcrReason');
    const patientId = (window.PATIENT_CONSENT_REQUEST || {}).patientId || '';

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const reason = reasonInput.value.trim();
        if (reason === '') return;

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        const originalHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

        fetch('../api/request-patient-consent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patientId: patientId, reason: reason }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    errorBox.textContent = (result.data && result.data.error) || 'Could not send this request. Please try again.';
                    errorBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                errorBox.textContent = 'Network error. Please try again.';
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
    });
});
