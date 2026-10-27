/* =============================================================
   EMAIL VERIFICATION — "Resend Email" (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   Loaded by all 6 roles' profile_settings.php pages, alongside the
   Firebase SDK script tags those pages now also load just for this.
   #btnResendVerification only exists in the page's own markup when
   $_SESSION['emailVerified'] is false (each role's own
   includes/<role>/profile_settings_*_firestore.php file), so this file
   does nothing at all once verified.

   firebase.auth().currentUser.sendEmailVerification() is the only way
   to actually SEND that email without this app having its own SMTP
   infrastructure — Firebase hosts it, same as forgot-password.php's
   sendPasswordResetEmail(). That needs the browser's client-side
   Firebase session to still be alive, which is separate from (and can
   outlive or expire independently of) this page's own PHP session —
   onAuthStateChanged() is used rather than reading .currentUser
   immediately, since the SDK restores that session asynchronously on
   page load. If it never fires with a real user (expired/never
   established — e.g. the PHP session outlived Firebase's own
   session-only sign-in), the button explains that instead of failing
   silently.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnResendVerification');
    if (!btn || typeof firebase === 'undefined') return;

    let currentUser = null;
    firebase.auth().onAuthStateChanged(function (user) {
        currentUser = user;
    });

    const defaultHtml = btn.innerHTML;

    btn.addEventListener('click', function () {
        if (!currentUser) {
            HCDialog.alert('Your browser sign-in session has expired. Please log out and back in, then try again.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending…';

        currentUser.sendEmailVerification()
            .then(function () {
                btn.innerHTML = '<i class="bi bi-check-lg"></i> Email Sent';
                HCDialog.alert('Verification email sent to ' + currentUser.email + '. Please check your inbox (and spam folder).');
            })
            .catch(function (error) {
                btn.disabled = false;
                btn.innerHTML = defaultHtml;
                if (error.code === 'auth/too-many-requests') {
                    HCDialog.alert('Too many attempts. Please wait a moment and try again.');
                    return;
                }
                HCDialog.alert('Something went wrong sending the verification email. Please try again.');
            });
    });
});
