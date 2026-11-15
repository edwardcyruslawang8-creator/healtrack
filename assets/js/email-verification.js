/* =============================================================
   EMAIL VERIFICATION — "Resend Email" (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   Loaded by all 6 roles' profile_settings.php pages. #btnResendVerification
   only exists in the page's own markup when $_SESSION['emailVerified']
   is false (each role's own includes/<role>/profile_settings_*_firestore.php
   file), so this file does nothing at all once verified.

   Posts to api/send-email-verification.php, which sends HEALTrack's own
   branded verification email server-side (includes/shared/email_verification_mailer.php)
   instead of the old firebase.auth().currentUser.sendEmailVerification()
   client call — no Firebase client SDK/session needed on this page any
   more for this feature.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnResendVerification');
    if (!btn) return;

    const defaultHtml = btn.innerHTML;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

        fetch('../api/send-email-verification.php', { method: 'POST' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.innerHTML = '<i class="bi bi-check-lg"></i> Email Sent';
                    HCDialog.alert('Verification email sent. Please check your inbox (and spam folder).');
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
