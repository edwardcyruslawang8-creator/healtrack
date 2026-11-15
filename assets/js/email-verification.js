/* =============================================================
   EMAIL VERIFICATION — "Resend Email" (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   Loaded by all 6 roles' profile_settings.php pages. #btnResendVerification
   only exists in the page's own markup when $_SESSION['emailVerified']
   is false (each role's own includes/<role>/profile_settings_*_firestore.php
   file), so this file does nothing at all once verified.

   "Resend Email" posts to api/send-email-verification.php, which sends
   HEALTrack's own branded verification email server-side
   (includes/shared/email_verification_mailer.php) instead of the old
   firebase.auth().currentUser.sendEmailVerification() client call.

   Since $_SESSION['emailVerified'] is only ever set once, at login,
   clicking the link in that email (in another tab, or the person's
   email client) never updates THIS open tab on its own — so this also
   polls api/check-email-verified.php (a LIVE Firebase Auth check) once
   on load and again every time the tab regains focus, and smoothly
   removes the whole "Email Verification" row the moment it comes back
   verified, instead of leaving it showing stale info until the next
   login.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnResendVerification');
    if (!btn) return;

    const row = btn.closest('.prf-security-row');
    const defaultHtml = btn.innerHTML;

    function removeRowSmoothly() {
        if (!row) return;
        row.style.maxHeight = row.scrollHeight + 'px';
        void row.offsetHeight; // force layout so the browser registers the starting height before animating
        row.classList.add('prf-security-row--leaving');
        row.style.maxHeight = '0px';
        row.addEventListener('transitionend', function handler(e) {
            if (e.propertyName === 'max-height') {
                row.removeEventListener('transitionend', handler);
                row.remove();
            }
        });
    }

    let checking = false;
    function checkVerifiedNow() {
        if (checking) return;
        checking = true;
        fetch('../api/check-email-verified.php')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                checking = false;
                if (data.verified) {
                    removeRowSmoothly();
                    window.removeEventListener('focus', checkVerifiedNow);
                    document.removeEventListener('visibilitychange', onVisibilityChange);
                }
            })
            .catch(function () { checking = false; });
    }
    function onVisibilityChange() {
        if (document.visibilityState === 'visible') checkVerifiedNow();
    }

    checkVerifiedNow();
    window.addEventListener('focus', checkVerifiedNow);
    document.addEventListener('visibilitychange', onVisibilityChange);

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

        fetch('../api/send-email-verification.php', { method: 'POST' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Email Sent';
                    HCDialog.alert('Verification email sent. Please check your inbox (and spam folder).');
                } else if (data.error && data.error.toLowerCase().indexOf('already verified') !== -1) {
                    removeRowSmoothly();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = defaultHtml;
                    HCDialog.alert(data.error || 'Something went wrong sending the verification email. Please try again.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = defaultHtml;
                HCDialog.alert('Something went wrong sending the verification email. Please try again.');
            });
    });
});
