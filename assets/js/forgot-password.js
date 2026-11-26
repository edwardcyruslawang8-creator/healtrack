/* =============================================================
   FORGOT PASSWORD PAGE JS (forgot-password.php)
   ---------------------------------------------------------------
   Posts to api/send-password-reset-email.php, which generates the real
   Firebase password-reset link server-side (Kreait Admin SDK) and
   emails it through this app's own branded mailer instead of letting
   Firebase's client SDK send its own generic email — see that file's
   header comment for why. The link it emails points straight at this
   app's own reset-password.php, where the actual "choose a new
   password" step happens (verifyPasswordResetCode()/
   confirmPasswordReset() in assets/js/reset-password.js) — see that
   file's header comment and api/report-password-reset-completed.php.

   Deliberately shows the SAME "check your email" success message
   whether the email belongs to a real account or not — surfacing that
   distinction would let this form be used to check which emails have
   real accounts (enumeration), so api/send-password-reset-email.php
   always returns the same generic success response. Only genuine
   client-side problems (bad email format, network failure) show their
   own real message, since neither reveals whether the account exists.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('fpEmail');
    const errorBox = document.getElementById('fpError');
    const successBox = document.getElementById('fpSuccess');
    const submitBtn = document.getElementById('fpSubmitBtn');

    if (!form) return;

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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessages();

        const email = emailInput.value.trim();
        if (!email) {
            showError('Please enter your email address.');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('That email address doesn\'t look right.');
            return;
        }

        setLoading(true);

        fetch('api/send-password-reset-email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
        })
            .then(function (res) { return res.json(); })
            .then(function () {
                // Always the same message, success or unknown-email alike —
                // see this file's header comment.
                setLoading(false);
                showSuccess(CHECK_EMAIL_MESSAGE);
                form.reset();
            })
            .catch(function () {
                setLoading(false);
                showError('Network error — please check your connection and try again.');
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
