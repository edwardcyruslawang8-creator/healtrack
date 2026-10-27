/* =============================================================
   RESET PASSWORD PAGE JS (reset-password.php)
   ---------------------------------------------------------------
   Loaded after firebase-app-compat.js, firebase-auth-compat.js,
   firebase-firestore-compat.js, and firebase-config.js — same loading
   order login.php/forgot-password.php use.

   Reached via the real link Firebase emails from
   assets/js/forgot-password.js's sendPasswordResetEmail() call
   (actionCodeSettings.handleCodeInApp: true routes it here instead of
   to Firebase's own default hosted page) — the URL carries a real
   ?oobCode=... query param this file verifies, then uses to actually
   change the password, both straight against Firebase Auth:
     1. verifyPasswordResetCode(oobCode) — confirms the code is real,
        unexpired, and unused, and returns which email it's for.
     2. confirmPasswordReset(oobCode, newPassword) — the actual change.
   Only once step 2 already succeeded does this call
   api/report-password-reset-completed.php (writes a real audit log
   entry) — best-effort from this
   page's own perspective, since the real password change already
   happened by then regardless of whether that call succeeds.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof firebase === 'undefined') return;

    const subtitle = document.getElementById('rpSubtitle');
    const errorBox = document.getElementById('rpError');
    const form = document.getElementById('resetPasswordForm');
    const formState = document.getElementById('rpFormState');
    const successState = document.getElementById('rpSuccessState');
    const newPasswordInput = document.getElementById('rpNewPassword');
    const confirmPasswordInput = document.getElementById('rpConfirmPassword');
    const submitBtn = document.getElementById('rpSubmitBtn');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    const params = new URLSearchParams(window.location.search);
    const oobCode = params.get('oobCode') || '';

    // Same generic "invalid or expired" wording regardless of the real
    // reason (missing code, already-used code, genuinely expired code)
    // — never reveals which specific thing was wrong, same principle
    // every other real link-validation flow in this app already follows.
    const INVALID_LINK_MESSAGE = 'This password reset link is invalid or has expired. Please request a new one from the Forgot Password page.';

    if (!oobCode) {
        subtitle.textContent = '';
        showError(INVALID_LINK_MESSAGE);
        return;
    }

    let verifiedEmail = '';

    firebase.auth().verifyPasswordResetCode(oobCode)
        .then(function (email) {
            verifiedEmail = email;
            subtitle.textContent = 'Choose a new password for ' + email + '.';
            form.classList.remove('d-none');
        })
        .catch(function () {
            subtitle.textContent = '';
            showError(INVALID_LINK_MESSAGE);
        });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.classList.add('d-none');

        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (newPassword.length < 6) {
            showError('Password must be at least 6 characters.');
            return;
        }
        if (newPassword !== confirmPassword) {
            showError('Passwords do not match.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Resetting...';

        firebase.auth().confirmPasswordReset(oobCode, newPassword)
            .then(function () {
                // Best-effort — the real password change already
                // succeeded above regardless of this call's outcome.
                fetch('api/report-password-reset-completed.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: verifiedEmail }),
                }).catch(function () { /* best-effort only */ });

                formState.classList.add('d-none');
                successState.classList.remove('d-none');
            })
            .catch(function (error) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Reset Password';

                if (error.code === 'auth/expired-action-code' || error.code === 'auth/invalid-action-code') {
                    showError(INVALID_LINK_MESSAGE);
                    return;
                }
                if (error.code === 'auth/weak-password') {
                    showError('Please choose a stronger password.');
                    return;
                }
                showError('Something went wrong resetting your password. Please try again.');
            });
    });
});
