/* =============================================================
   FORGOT PASSWORD PAGE JS (forgot-password.php)
   ---------------------------------------------------------------
   Loaded after firebase-app-compat.js, firebase-auth-compat.js,
   firebase-firestore-compat.js, and firebase-config.js — same loading
   order login.php uses. firebase.auth().sendPasswordResetEmail(email,
   actionCodeSettings) generates the reset link and emails it — the
   actual "choose a new password" step now happens on THIS app's own
   reset-password.php (actionCodeSettings.handleCodeInApp: true routes
   the email link there instead of to Firebase's default hosted page),
   so there's a real moment to record an audit log entry once a
   password genuinely gets reset — see reset-password.php's own header
   comment and api/report-password-reset-completed.php.

   Deliberately shows the SAME "check your email" success message
   whether sendPasswordResetEmail() succeeds OR fails with
   'auth/user-not-found' — surfacing that specific failure would let
   this form be used to check which emails have real accounts
   (enumeration), so it's treated as a success case instead. Every
   OTHER error (bad email format, network failure, rate limit) still
   shows its own real message, since none of those reveal whether the
   account exists.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('fpEmail');
    const errorBox = document.getElementById('fpError');
    const successBox = document.getElementById('fpSuccess');
    const submitBtn = document.getElementById('fpSubmitBtn');

    if (!form || typeof firebase === 'undefined') return;

    function hideMessages() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        successBox.classList.add('d-none');
        successBox.textContent = '';
    }
    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }
    function showSuccess(message) {
        successBox.textContent = message;
        successBox.classList.remove('d-none');
    }
    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm"></span> Sending...'
            : '<i class="bi bi-send-fill"></i> Send Reset Link';
    }

    const CHECK_EMAIL_MESSAGE = 'If an account exists for that email, a password reset link has been sent. Please check your inbox (and spam folder).';

    // Fire-and-forget — api/report-password-reset-request.php (the only
    // backend touchpoint this otherwise pure-client-side Firebase flow
    // has) writes a real audit log entry when the email belongs to a
    // real account, silently no-ops otherwise. Never awaited/read, so
    // it can't change or delay what the visitor sees either way.
    function reportResetRequest(email) {
        fetch('api/report-password-reset-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
        }).catch(function () { /* best-effort only */ });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessages();

        const email = emailInput.value.trim();
        if (!email) {
            showError('Please enter your email address.');
            return;
        }

        setLoading(true);

        const actionCodeSettings = {
            url: window.location.origin + (window.APP_BASE_URL || '') + '/reset-password.php',
            handleCodeInApp: true,
        };

        firebase.auth().sendPasswordResetEmail(email, actionCodeSettings)
            .then(function () {
                setLoading(false);
                showSuccess(CHECK_EMAIL_MESSAGE);
                reportResetRequest(email);
                form.reset();
            })
            .catch(function (error) {
                setLoading(false);
                if (error.code === 'auth/user-not-found') {
                    // Same message as success — see this file's header comment.
                    // Still reported: report-password-reset-request.php applies
                    // its own identical no-op-for-unknown-email rule, so this
                    // can't be used to confirm the account doesn't exist either.
                    showSuccess(CHECK_EMAIL_MESSAGE);
                    reportResetRequest(email);
                    form.reset();
                    return;
                }
                if (error.code === 'auth/invalid-email') {
                    showError('That email address doesn\'t look right.');
                    return;
                }
                if (error.code === 'auth/too-many-requests') {
                    showError('Too many attempts. Please wait a moment and try again.');
                    return;
                }
                if (error.code === 'auth/network-request-failed') {
                    showError('Network error — please check your connection and try again.');
                    return;
                }
                showError('Something went wrong sending the reset link. Please try again.');
            });
    });

    /* ---------- Alternative mode: reset with an authenticator code
       (api/reset-password-with-totp.php) — for accounts with 2FA
       already enabled, doesn't depend on any email ever arriving. ---------- */
    const emailLinkMode = document.getElementById('fpEmailLinkMode');
    const totpMode = document.getElementById('fpTotpMode');
    const showTotpModeLink = document.getElementById('showTotpModeLink');
    const showEmailModeLink = document.getElementById('showEmailModeLink');

    if (showTotpModeLink && showEmailModeLink && emailLinkMode && totpMode) {
        showTotpModeLink.addEventListener('click', function (e) {
            e.preventDefault();
            emailLinkMode.classList.add('d-none');
            totpMode.classList.remove('d-none');
        });
        showEmailModeLink.addEventListener('click', function (e) {
            e.preventDefault();
            totpMode.classList.add('d-none');
            emailLinkMode.classList.remove('d-none');
        });
    }

    const totpForm = document.getElementById('totpResetForm');
    if (totpForm) {
        const trErrorBox = document.getElementById('trError');
        const trSuccessBox = document.getElementById('trSuccess');
        const trSubmitBtn = document.getElementById('trSubmitBtn');

        function trShowError(message) {
            trSuccessBox.classList.add('d-none');
            trErrorBox.textContent = message;
            trErrorBox.classList.remove('d-none');
        }
        function trSetLoading(isLoading) {
            trSubmitBtn.disabled = isLoading;
            trSubmitBtn.innerHTML = isLoading
                ? '<span class="spinner-border spinner-border-sm"></span> Resetting...'
                : '<i class="bi bi-check-circle-fill"></i> Reset Password';
        }

        totpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            trErrorBox.classList.add('d-none');

            const payload = {
                email: document.getElementById('trEmail').value.trim(),
                code: document.getElementById('trCode').value.trim(),
                newPassword: document.getElementById('trNewPassword').value,
                confirmPassword: document.getElementById('trConfirmPassword').value,
            };

            if (payload.newPassword !== payload.confirmPassword) {
                trShowError('New passwords do not match.');
                return;
            }

            trSetLoading(true);

            fetch('api/reset-password-with-totp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    trSetLoading(false);
                    if (!result.ok || !result.data.success) {
                        trShowError(result.data.error || 'Something went wrong. Please try again.');
                        return;
                    }
                    totpForm.classList.add('d-none');
                    trErrorBox.classList.add('d-none');
                    trSuccessBox.textContent = 'Your password has been reset. You can now sign in with your new password.';
                    trSuccessBox.classList.remove('d-none');
                })
                .catch(function () {
                    trSetLoading(false);
                    trShowError('Network error — please check your connection and try again.');
                });
        });
    }
});
