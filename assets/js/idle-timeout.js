/* =============================================================
   IDLE-SESSION AUTO-LOGOUT (every authenticated page)
   ---------------------------------------------------------------
   Injected automatically right before </body> on every authenticated
   page by config/constants.php's output-buffering callback — see that
   file and includes/shared/idle_timeout.php's header comment for the
   full design (this is the CLIENT-SIDE half: proactively redirects to
   api/logout.php after 30 real minutes of no mouse/keyboard/touch/
   scroll activity, without waiting for the user's next page request;
   idle_timeout.php is the server-side, authoritative half that still
   catches a stale session even if this script never got the chance to
   fire).

   Synced across tabs via localStorage: real activity in ANY tab of
   this browser resets the countdown in every tab (shared timestamp),
   and a timeout firing in one tab redirects the others too (storage
   event) — someone actively working in one tab shouldn't get logged
   out just because a second, idle tab hit the limit on its own.
   ============================================================= */
(function () {
    var TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes — must match includes/shared/idle_timeout.php's HC_IDLE_TIMEOUT_SECONDS
    var WARNING_MS = 2 * 60 * 1000;  // show the "you're about to be signed out" warning 2 minutes early
    var CHECK_INTERVAL_MS = 5000;
    var ACTIVITY_STORAGE_KEY = 'hc_last_activity';
    var LOGOUT_STORAGE_KEY = 'hc_idle_logged_out';
    var LOGOUT_URL = (window.HC_IDLE_BASE_URL || '') + '/api/logout.php?reason=idle';

    var warningEl = null;
    var countdownInterval = null;
    var activityThrottled = false;

    function now() {
        return Date.now();
    }

    function getLastActivity() {
        var stored = parseInt(localStorage.getItem(ACTIVITY_STORAGE_KEY), 10);
        return isNaN(stored) ? now() : stored;
    }

    function recordActivity() {
        try {
            localStorage.setItem(ACTIVITY_STORAGE_KEY, String(now()));
        } catch (e) { /* private-browsing/storage-full — timer still works for this tab alone */ }
        hideWarning();
    }

    function doLogout() {
        try {
            localStorage.setItem(LOGOUT_STORAGE_KEY, String(now()));
        } catch (e) { /* ignore — this tab still redirects either way */ }
        window.location.href = LOGOUT_URL;
    }

    function hideWarning() {
        if (warningEl) warningEl.style.display = 'none';
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    function showWarning() {
        if (!warningEl) {
            warningEl = document.createElement('div');
            warningEl.id = 'hcIdleWarning';
            warningEl.setAttribute('role', 'alertdialog');
            warningEl.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1F2937;color:#fff;'
                + 'padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3);'
                + 'font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:320px;';
            warningEl.innerHTML =
                '<div style="margin-bottom:12px;">'
                + 'You’ve been inactive. For your security, you’ll be signed out in '
                + '<strong id="hcIdleCountdown">2:00</strong>.'
                + '</div>'
                + '<button type="button" id="hcIdleStayBtn" style="background:#D93025;color:#fff;border:none;'
                + 'border-radius:6px;padding:7px 16px;cursor:pointer;font-weight:600;font-size:13px;">'
                + 'Stay signed in</button>';
            document.body.appendChild(warningEl);
            document.getElementById('hcIdleStayBtn').addEventListener('click', recordActivity);
        }
        warningEl.style.display = 'block';

        var countdownEl = document.getElementById('hcIdleCountdown');
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(function () {
            var remaining = Math.max(0, (getLastActivity() + TIMEOUT_MS) - now());
            var mins = Math.floor(remaining / 60000);
            var secs = Math.floor((remaining % 60000) / 1000);
            countdownEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                doLogout();
            }
        }, 1000);
    }

    function tick() {
        var elapsed = now() - getLastActivity();
        if (elapsed >= TIMEOUT_MS) {
            doLogout();
        } else if (elapsed >= TIMEOUT_MS - WARNING_MS) {
            showWarning();
        } else {
            hideWarning();
        }
    }

    // Real user interaction only, throttled to once/second — this must
    // stay cheap since mousemove/scroll can fire dozens of times a
    // second otherwise.
    ['mousemove', 'keydown', 'mousedown', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            if (activityThrottled) return;
            activityThrottled = true;
            setTimeout(function () { activityThrottled = false; }, 1000);
            recordActivity();
        }, { passive: true });
    });

    // Another tab's timer fired — follow it here too instead of waiting
    // for this tab's own countdown.
    window.addEventListener('storage', function (e) {
        if (e.key === LOGOUT_STORAGE_KEY && e.newValue) {
            window.location.href = LOGOUT_URL;
        }
    });

    if (localStorage.getItem(ACTIVITY_STORAGE_KEY) === null) {
        recordActivity();
    }

    setInterval(tick, CHECK_INTERVAL_MS);
})();
