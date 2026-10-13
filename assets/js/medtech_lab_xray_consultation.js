/* =============================================================
   MEDICAL TECHNICIAN LAB & X-RAY CONSULTATION JS
   (medical_technician/lab_xray_consultation.php)
   ---------------------------------------------------------------
   This page has no other interactivity of its own (every action is a
   plain <a href> link or GET filter form) — the one thing here is a
   background poll of api/medtech-referral-queue-status.php that
   refreshes the stat cards (#lxcReferralStats) and the referrals table
   (#lxcReferralTable) in place, so a referral a doctor just sent (or
   one a technician somewhere else picked up/completed) shows up here
   without a manual reload.

   The two sections are refreshed INDEPENDENTLY (own container, own
   data-signature) rather than as one combined swap — the filter
   toolbar's search input sits in the DOM between them, and a poll must
   never touch it, since the technician could be mid-typing when it
   fires. Passes window.location.search through unchanged so the polled
   snapshot always matches whatever type/search/date/page filter is
   currently on screen.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const statsEl = document.getElementById('lxcReferralStats');
    const tableEl = document.getElementById('lxcReferralTable');
    if (!statsEl && !tableEl) return;

    const POLL_INTERVAL_MS = 15000;
    let pollTimer = null;

    function flash(el) {
        el.classList.add('lxc-queue-updated');
        window.setTimeout(function () { el.classList.remove('lxc-queue-updated'); }, 1500);
    }

    function pollQueueStatus() {
        fetch('../api/medtech-referral-queue-status.php' + window.location.search)
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (result) {
                if (!result || !result.success) return;

                if (statsEl && result.statsSignature !== statsEl.dataset.signature) {
                    statsEl.innerHTML = result.statsHtml;
                    statsEl.dataset.signature = result.statsSignature;
                    flash(statsEl);
                }
                if (tableEl && result.tableSignature !== tableEl.dataset.signature) {
                    tableEl.innerHTML = result.tableHtml;
                    tableEl.dataset.signature = result.tableSignature;
                    flash(tableEl);
                }
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

    // Paused while this tab isn't visible, catches up immediately when
    // it becomes visible again instead of waiting for the next tick —
    // same pattern as assets/js/doctor_consultations.js's own live poll.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPolling();
        } else {
            pollQueueStatus();
            startPolling();
        }
    });

    if (!document.hidden) startPolling();
});
