/* =============================================================
   RADIO TECHNOLOGIST X-RAY CONSULTATION JS
   (radio_technologist/xray_consultation.php)
   ---------------------------------------------------------------
   This page has no other interactivity of its own (every action is a
   plain <a href> link or GET filter form) — the one thing here is a
   background poll of api/radiotech-xray-queue-status.php that
   refreshes the stat card (#xcReferralStats) and the referrals table
   (#xcReferralTable) in place, so a referral a doctor just sent (or
   one picked up/completed elsewhere) shows up here without a manual
   reload. Identical mechanism to assets/js/medtech_lab_xray_consultation.js,
   just this role's own container ids/endpoint.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const statsEl = document.getElementById('xcReferralStats');
    const tableEl = document.getElementById('xcReferralTable');
    if (!statsEl && !tableEl) return;

    const POLL_INTERVAL_MS = 15000;
    let pollTimer = null;

    function flash(el) {
        el.classList.add('lxc-queue-updated');
        window.setTimeout(function () { el.classList.remove('lxc-queue-updated'); }, 1500);
    }

    function pollQueueStatus() {
        fetch('../api/radiotech-xray-queue-status.php' + window.location.search)
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
