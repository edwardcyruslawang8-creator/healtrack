/* =============================================================
   DOCTOR CONSULTATIONS JS (doctor/consultations.php)
   ---------------------------------------------------------------
   Two things: "Start Consultation" in the "Next to Consult" highlight
   card, which opens the "Starting Consultation" modal
   (#startConsultationModal) — populated purely from the button's own
   data-* attributes (server-set from $nextConsult/$nextPatient/
   $nextType/$nextStatus), no extra network request — and a background
   poll of api/doctor-queue-status.php that refreshes #cnsQueueStatus
   (Patient Information / Ongoing Consultation / Next to Consult) in
   place, so a consultation medical staff just set for this doctor
   shows up here without a manual reload. Everything else (filters,
   search, pagination) is a plain GET form/link — same pattern as every
   other filter toolbar in this app.

   The "Start Consultation" button lives INSIDE #cnsQueueStatus, whose
   innerHTML gets replaced wholesale by a live refresh — so its click
   handling is delegated on `document` (not attached to the button
   directly) both to survive that replacement and to work even when no
   button exists yet at page load (an empty queue that then gets a new
   consultation).

   Inside the modal, "Proceed with Consultation" (the option card and
   the footer button both do the exact same thing) POSTs to
   api/update-consultation.php flipping the consultation to "ongoing"
   (stamping a real "startedAt" server-side, first time only), then —
   unlike a plain status flip elsewhere in this app — redirects into
   doctor/active_consultation.php?id=..., the actual clinical
   documentation workspace, instead of reloading this list page.
   "View Patient History" is a real link (patient_profile.php?id=...)
   that starts nothing — it just lets the doctor check the chart first
   and come back.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;

    const modalEl = document.getElementById('startConsultationModal');
    const modal = new bootstrap.Modal(modalEl);
    const proceedBtn = document.getElementById('scmProceedBtn');
    const proceedCard = document.getElementById('scmProceedCard');
    const historyCard = document.getElementById('scmHistoryCard');
    const queueStatusEl = document.getElementById('cnsQueueStatus');

    // The #btnStartConsultation that was actually clicked to open the
    // modal — proceed() below needs its data-id, and re-reading
    // getElementById('btnStartConsultation') at that point could return
    // a DIFFERENT element (or none) if a live queue refresh swapped the
    // DOM in between opening the modal and clicking Proceed.
    let activeStartBtn = null;

    document.addEventListener('click', function (e) {
        const startBtn = e.target.closest('#btnStartConsultation');
        if (!startBtn) return;

        activeStartBtn = startBtn;
        const d = startBtn.dataset;

        document.getElementById('scmPatientName').textContent = d.patientName;
        document.getElementById('scmPatientMeta').textContent = d.patientAge + ' years old (' + d.patientGender + ') • ' + d.patientId;

        const typeBadge = document.getElementById('scmTypeBadge');
        typeBadge.textContent = d.typeLabel;
        typeBadge.style.background = d.typeBg;
        typeBadge.style.color = d.typeColor;

        document.getElementById('scmArrivalTime').textContent = d.arrivalTime;
        document.getElementById('scmReason').textContent = d.reason;
        document.getElementById('scmStatusLabel').textContent = d.statusLabel;
        document.getElementById('scmStatusDot').style.color = d.statusColor;

        // &from=consultation — patient_profile.php's back link/breadcrumb
        // point back to consultations.php instead of their usual default
        // (patient_management.php) whenever this param is present, so a
        // doctor checking history from here has a real way back.
        historyCard.href = 'patient_profile.php?id=' + encodeURIComponent(d.patientId) + '&from=consultation';

        modal.show();
    });

    function proceed() {
        if (!activeStartBtn) return;
        proceedBtn.disabled = true;
        proceedCard.disabled = true;
        const defaultHtml = proceedBtn.innerHTML;
        proceedBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Starting…';

        fetch('../api/update-consultation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: activeStartBtn.dataset.id, status: 'ongoing' }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.href = 'active_consultation.php?id=' + encodeURIComponent(activeStartBtn.dataset.id);
            })
            .catch(function (err) {
                HCDialog.alert(err.message);
                proceedBtn.disabled = false;
                proceedCard.disabled = false;
                proceedBtn.innerHTML = defaultHtml;
            });
    }

    proceedBtn.addEventListener('click', proceed);
    proceedCard.addEventListener('click', proceed);

    // -----------------------------------------------------------
    // Live queue refresh — polls api/doctor-queue-status.php and swaps
    // #cnsQueueStatus's innerHTML only when its nextId/ongoingId
    // actually differ from what's already shown, so most ticks touch
    // nothing. Paused while this tab isn't visible (no point polling a
    // background tab), and catches up immediately when it becomes
    // visible again instead of waiting for the next tick.
    // -----------------------------------------------------------
    if (queueStatusEl) {
        const POLL_INTERVAL_MS = 15000;
        let pollTimer = null;

        function pollQueueStatus() {
            fetch('../api/doctor-queue-status.php')
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (result) {
                    if (!result || !result.success) return;
                    const changed = result.nextId !== queueStatusEl.dataset.nextId || result.ongoingId !== queueStatusEl.dataset.ongoingId;
                    if (!changed) return;

                    queueStatusEl.innerHTML = result.html;
                    queueStatusEl.dataset.nextId = result.nextId;
                    queueStatusEl.dataset.ongoingId = result.ongoingId;

                    // Brief highlight so an in-place update doesn't feel
                    // silent/easy to miss (assets/css/consultations.css).
                    queueStatusEl.classList.add('cns-queue-updated');
                    window.setTimeout(function () { queueStatusEl.classList.remove('cns-queue-updated'); }, 1500);
                })
                .catch(function () { /* transient network hiccup — just try again next tick */ });
        }

        function startPolling() {
            if (pollTimer) return;
            pollTimer = window.setInterval(pollQueueStatus, POLL_INTERVAL_MS);
        }
        function stopPolling() {
            if (!pollTimer) return;
            window.clearInterval(pollTimer);
            pollTimer = null;
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                pollQueueStatus();
                startPolling();
            }
        });

        if (!document.hidden) startPolling();
    }
});
