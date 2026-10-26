/* =============================================================
   EMAIL VERIFICATION REMINDER TOAST (every authenticated page where
   the signed-in account's email isn't verified yet, for the roles
   that have a Security Settings page)
   ---------------------------------------------------------------
   Injected right after includes/shared/email_verification_reminder.php's
   hc_render_email_verification_toast() sets window.HC_EMAIL_VERIFY_TOAST
   and loads this file — same design as assets/js/two-fa-reminder-toast.js,
   this is just its sibling for a different real condition. Show a
   bottom-right toast once per calendar day (tracked in localStorage,
   keyed by uid + today's date), auto-hide it after
   HC_EMAIL_VERIFY_TOAST.autoHideMs, and let the person dismiss it or
   jump straight to HC_EMAIL_VERIFY_TOAST.profileUrl to verify now.
   ============================================================= */
(function () {
    var config = window.HC_EMAIL_VERIFY_TOAST;
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
        el.id = 'hcEmailVerifyToast';
        el.setAttribute('role', 'alertdialog');
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1F2937;color:#fff;'
            + 'padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3);'
            + 'font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:320px;';
        el.innerHTML =
            '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;">'
            + '<i class="bi bi-envelope-exclamation-fill" style="color:#E8710A;font-size:18px;line-height:1.5;"></i>'
            + '<div>Your account’s email address isn’t verified yet. Verify it from Security Settings to keep your account secure.</div>'
            + '</div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;">'
            + '<button type="button" id="hcEmailVerifyToastDismiss" style="background:transparent;color:#D1D5DB;border:none;'
            + 'padding:7px 12px;cursor:pointer;font-weight:600;font-size:13px;">Dismiss</button>'
            + '<a href="' + config.profileUrl + '" id="hcEmailVerifyToastVerify" style="background:#2563EB;color:#fff;border:none;'
            + 'border-radius:6px;padding:7px 16px;cursor:pointer;font-weight:600;font-size:13px;text-decoration:none;display:inline-block;">'
            + 'Verify now</a>'
            + '</div>';
        document.body.appendChild(el);

        function close() {
            if (el.parentNode) el.parentNode.removeChild(el);
        }

        document.getElementById('hcEmailVerifyToastDismiss').addEventListener('click', close);

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
