/* =============================================================
   2FA SETUP REMINDER TOAST (every authenticated page where 2FA is
   still off, for the roles that have a Security Settings page)
   ---------------------------------------------------------------
   Injected right after includes/shared/two_fa_reminder.php's
   hc_render_2fa_reminder_toast() sets window.HC_2FA_TOAST and loads
   this file — see that function's header comment for the full
   design. This is just the client half: show a bottom-right toast
   once per calendar day (tracked in localStorage, keyed by uid +
   today's date, same idiom as idle-timeout.js's activity tracking),
   auto-hide it after HC_2FA_TOAST.autoHideMs, and let the person
   dismiss it or jump straight to HC_2FA_TOAST.profileUrl to enable
   2FA now.
   ============================================================= */
(function () {
    var config = window.HC_2FA_TOAST;
    if (!config || !config.storageKey) return;

    try {
        if (localStorage.getItem(config.storageKey)) return; // already shown today
    } catch (e) { /* private-browsing/storage-full — show anyway, just won't remember for next load */ }

    function markShown() {
        try {
            localStorage.setItem(config.storageKey, '1');
        } catch (e) { /* ignore */ }
    }

    function render() {
        var el = document.createElement('div');
        el.id = 'hc2faToast';
        el.setAttribute('role', 'alertdialog');
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1F2937;color:#fff;'
            + 'padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3);'
            + 'font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:320px;';
        el.innerHTML =
            '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;">'
            + '<i class="bi bi-shield-exclamation" style="color:#E8710A;font-size:18px;line-height:1.5;"></i>'
            + '<div>Your account doesn’t have two-factor authentication enabled yet. Add it from Security Settings for stronger account protection.</div>'
            + '</div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;">'
            + '<button type="button" id="hc2faToastDismiss" style="background:transparent;color:#D1D5DB;border:none;'
            + 'padding:7px 12px;cursor:pointer;font-weight:600;font-size:13px;">Dismiss</button>'
            + '<a href="' + config.profileUrl + '" id="hc2faToastEnable" style="background:#2563EB;color:#fff;border:none;'
            + 'border-radius:6px;padding:7px 16px;cursor:pointer;font-weight:600;font-size:13px;text-decoration:none;display:inline-block;">'
            + 'Enable now</a>'
            + '</div>';
        document.body.appendChild(el);

        function close() {
            if (el.parentNode) el.parentNode.removeChild(el);
        }

        document.getElementById('hc2faToastDismiss').addEventListener('click', close);

        if (config.autoHideMs) {
            setTimeout(close, config.autoHideMs);
        }

        markShown();
    }

    if (document.body) {
        render();
    } else {
        document.addEventListener('DOMContentLoaded', render);
    }
})();
