/* =============================================================
   PATIENT CONSENT GATE (subadmin/medical-staff/doctor's own
   patient_management.php)
   ---------------------------------------------------------------
   Intercepts every click on a real "View Profile" link — both the
   table's own .pmg-btn-view-profile button AND Quick Search's
   dynamically-rendered result links (assets/js/quick-search.js renders
   those as plain <a href="patient_profile.php?id=..."> too, so one
   delegated selector on the href pattern covers both without needing
   two separate handlers) — via event delegation on document, since
   Quick Search's results don't exist in the DOM until typed.
   api/get-patient-consent-status.php decides, before navigating
   anywhere, whether real access already exists (lets the click
   through to patient_profile.php as normal) or not (opens
   includes/shared/patient_consent_request_modal.php in place instead,
   no extra page load). This is a UX convenience only — patient_profile.php
   re-runs the exact same real check server-side regardless, so
   skipping this (e.g. a direct URL) still gates correctly on its own.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('pcgModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const nameEl = document.getElementById('pcgPatientName');
    const pendingStatus = document.getElementById('pcgPendingStatus');
    const pendingText = document.getElementById('pcgPendingText');
    const declinedStatus = document.getElementById('pcgDeclinedStatus');
    const declinedText = document.getElementById('pcgDeclinedText');
    const errorBox = document.getElementById('pcgError');
    const form = document.getElementById('pcgForm');
    const reasonInput = document.getElementById('pcgReason');
    const submitBtn = document.getElementById('pcgSubmitBtn');
    const sentState = document.getElementById('pcgSentState');

    let currentPatientId = null;

    function formatDate(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function setFormVisible(visible) {
        // The submit button lives in the modal footer (form="pcgForm"),
        // outside <form id="pcgForm"> itself, so hiding the form alone
        // wouldn't hide it — both are toggled together here.
        form.classList.toggle('d-none', !visible);
        submitBtn.classList.toggle('d-none', !visible);
    }

    function resetModal() {
        errorBox.classList.add('d-none');
        pendingStatus.classList.add('d-none');
        declinedStatus.classList.add('d-none');
        sentState.classList.add('d-none');
        setFormVisible(true);
        reasonInput.value = '';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Send Consent Request';
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="patient_profile.php?id="]');
        if (!link) return;

        const url = new URL(link.href, window.location.href);
        const patientId = url.searchParams.get('id');
        if (!patientId) return;

        e.preventDefault();
        currentPatientId = patientId;

        fetch('../api/get-patient-consent-status.php?patientId=' + encodeURIComponent(patientId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    window.location.href = link.href; // let patient_profile.php itself show the real not-found state
                    return;
                }
                if (data.access) {
                    window.location.href = link.href;
                    return;
                }

                resetModal();
                nameEl.textContent = data.patientName || 'This patient';

                if (data.pendingRequest) {
                    pendingText.textContent = 'Request sent on ' + formatDate(data.pendingRequest.givenOn) + ' — awaiting the patient\'s response.';
                    pendingStatus.classList.remove('d-none');
                    setFormVisible(false);
                } else {
                    if (data.declinedRequest) {
                        declinedText.textContent = 'Previously declined on ' + formatDate(data.declinedRequest.respondedAt || data.declinedRequest.givenOn) + '. You may send a new request.';
                        declinedStatus.classList.remove('d-none');
                    }
                }

                modal.show();
            })
            .catch(function () {
                window.location.href = link.href; // network hiccup — fall back to the real page, which re-checks anyway
            });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const reason = reasonInput.value.trim();
        if (reason === '' || !currentPatientId) return;

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

        fetch('../api/request-patient-consent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patientId: currentPatientId, reason: reason }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    errorBox.textContent = (result.data && result.data.error) || 'Could not send this request. Please try again.';
                    errorBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Send Consent Request';
                    return;
                }
                setFormVisible(false);
                sentState.classList.remove('d-none');
            })
            .catch(function () {
                errorBox.textContent = 'Network error. Please try again.';
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Send Consent Request';
            });
    });
});
