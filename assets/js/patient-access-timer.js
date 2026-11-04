/* =============================================================
   PATIENT-PROFILE ACCESS COUNTDOWN (1-hour consent window)
   ---------------------------------------------------------------
   Injected automatically right before </body>, same late-stage
   output-buffer callback in config/constants.php that already injects
   idle-timeout.js and the reminder toasts — see that file's header
   comment. A no-op unless window.HC_PATIENT_ACCESS_EXPIRES_AT was set
   (only true on a doctor/medical-staff patient-profile tab/detail page
   where this session's own access is a real, time-boxed accepted
   consent — subadmin/registrant-today/consultation-today access has no
   deadline and never sets this).

   Unlike idle-timeout.js, this isn't reset by activity — it's a fixed
   server-computed deadline (1 hour after this staff member's first
   real access), so there's no "stay signed in" button, just a warning
   once 5 minutes are left and a hard redirect the instant it hits zero,
   even mid-session — matching this app's existing idle-timeout
   auto-logout-at-zero behavior.
   ============================================================= */
(function () {
    var expiresAt = window.HC_PATIENT_ACCESS_EXPIRES_AT;
    if (!expiresAt) return;

    var WARNING_MS = 5 * 60 * 1000;
    var CHECK_INTERVAL_MS = 1000;
    var redirectUrl = window.HC_PATIENT_ACCESS_REDIRECT_URL || 'patient_profile.php';

    var warningEl = null;

    function now() {
        return Date.now();
    }

    function doRedirect() {
        window.location.href = redirectUrl;
    }

    function showWarning(remaining) {
        if (!warningEl) {
            warningEl = document.createElement('div');
            warningEl.id = 'hcPatientAccessWarning';
            warningEl.setAttribute('role', 'alertdialog');
            warningEl.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1F2937;color:#fff;'
                + 'padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3);'
                + 'font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:320px;';
            warningEl.innerHTML =
                '<div>This patient\'s profile will lock again in '
                + '<strong id="hcPatientAccessCountdown"></strong> — '
                + 'you\'ll need to request access again after that.</div>';
            document.body.appendChild(warningEl);
        }
        var mins = Math.floor(remaining / 60000);
        var secs = Math.floor((remaining % 60000) / 1000);
        document.getElementById('hcPatientAccessCountdown').textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function tick() {
        var remaining = expiresAt - now();
        if (remaining <= 0) {
            doRedirect();
            return;
        }
        if (remaining <= WARNING_MS) {
            showWarning(remaining);
        }
    }

    tick();
    setInterval(tick, CHECK_INTERVAL_MS);
})();
