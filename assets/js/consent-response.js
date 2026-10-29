/* =============================================================
   CONSENT RESPONSE PAGE JS (consent-response.php)
   ---------------------------------------------------------------
   Approve/Decline — POSTs to api/respond-patient-consent.php with the
   real token this page itself was reached with (window.CONSENT_RESPONSE_TOKEN).
   No session, no login — the token itself is the only credential.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const pendingView = document.getElementById('crPendingView');
    const doneView = document.getElementById('crDoneView');
    const doneHeading = document.getElementById('crDoneHeading');
    const doneMessage = document.getElementById('crDoneMessage');
    const errorBox = document.getElementById('crError');
    const approveBtn = document.getElementById('crApproveBtn');
    const declineBtn = document.getElementById('crDeclineBtn');
    const token = window.CONSENT_RESPONSE_TOKEN || '';
    const BASE_URL = window.APP_BASE_URL || '';

    if (!pendingView || !approveBtn || !declineBtn) return;

    function submitDecision(decision, btn) {
        errorBox.classList.add('d-none');
        approveBtn.disabled = true;
        declineBtn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting…';

        fetch(BASE_URL + '/api/respond-patient-consent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token, decision: decision }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    errorBox.textContent = (result.data && result.data.error) || 'Something went wrong. Please try again.';
                    errorBox.classList.remove('d-none');
                    approveBtn.disabled = false;
                    declineBtn.disabled = false;
                    btn.innerHTML = originalHtml;
                    return;
                }
                pendingView.classList.add('d-none');
                doneView.classList.remove('d-none');
                if (decision === 'accepted') {
                    doneHeading.textContent = 'Access approved';
                    doneMessage.textContent = 'The staff member can now view your patient details.';
                } else {
                    doneHeading.textContent = 'Request declined';
                    doneMessage.textContent = 'The staff member has not been given access to your patient details.';
                }
            })
            .catch(function () {
                errorBox.textContent = 'Network error. Please try again.';
                errorBox.classList.remove('d-none');
                approveBtn.disabled = false;
                declineBtn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    }

    approveBtn.addEventListener('click', function () { submitDecision('accepted', approveBtn); });
    declineBtn.addEventListener('click', function () { submitDecision('rejected', declineBtn); });
});
