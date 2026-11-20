/* =============================================================
   Shared Queue Poll Helper (HCQueuePoll)
   ---------------------------------------------------------------
   Generic background poll for a page with one or more "pending
   items" sections (a stat-card block, a table) that should refresh
   themselves in place without a manual reload — same interval/
   visibility-pause/signature-diff pattern first built independently
   for doctor/consultations.php, medical_technician/lab_xray_consultation.php,
   and radiologic_technologist/xray_consultation.php, extracted here
   so new live-refresh pages don't each hand-duplicate it again.

   Usage:
     HCQueuePoll.start({
         endpoint: '../api/foo-queue-status.php',
         sections: [
             { el: document.getElementById('fooStats'), htmlKey: 'statsHtml', sigKey: 'statsSignature' },
             { el: document.getElementById('fooTable'), htmlKey: 'tableHtml', sigKey: 'tableSignature' },
         ],
     });

   - `passQuery` (default true) appends window.location.search to the
     endpoint URL, so the polled snapshot matches whatever filter/page
     is currently on screen (same reasoning as the 3 existing pages).
   - Each section's current signature lives in its own
     el.dataset.signature — the matching server-rendered page must set
     that same initial value when the section first renders, same as
     the 3 existing pages' own data-signature attributes.
   - A section missing its `el` (page doesn't have that part) is
     silently skipped, not an error.
   - Paused while the tab isn't visible, catches up immediately when
     it becomes visible again instead of waiting for the next tick.
   ============================================================= */
(function () {
    function start(options) {
        const endpoint = options.endpoint;
        const sections = (options.sections || []).filter(function (s) { return s.el; });
        if (!endpoint || sections.length === 0) return;

        const intervalMs = options.intervalMs || 15000;
        const flashClass = options.flashClass || 'hc-queue-updated';
        const passQuery = options.passQuery !== false;
        let pollTimer = null;

        function flash(el) {
            el.classList.add(flashClass);
            window.setTimeout(function () { el.classList.remove(flashClass); }, 1500);
        }

        function poll() {
            const url = endpoint + (passQuery ? window.location.search : '');
            fetch(url)
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (result) {
                    if (!result || !result.success) return;
                    sections.forEach(function (s) {
                        const sig = result[s.sigKey];
                        if (sig === undefined || sig === s.el.dataset.signature) return;
                        s.el.innerHTML = result[s.htmlKey];
                        s.el.dataset.signature = sig;
                        flash(s.el);
                        if (typeof options.onUpdate === 'function') options.onUpdate(s);
                    });
                })
                .catch(function () { /* transient network hiccup — just try again next tick */ });
        }

        function startPolling() {
            if (pollTimer) return;
            pollTimer = window.setInterval(poll, intervalMs);
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
                poll();
                startPolling();
            }
        });

        if (!document.hidden) startPolling();
    }

    window.HCQueuePoll = { start: start };
})();
