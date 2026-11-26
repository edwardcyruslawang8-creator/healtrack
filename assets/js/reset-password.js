/* =============================================================
   RESET PASSWORD PAGE JS (reset-password.php)
   ---------------------------------------------------------------
   Loaded after firebase-app-compat.js, firebase-auth-compat.js,
   firebase-firestore-compat.js, and firebase-config.js — same loading
   order login.php/forgot-password.php use.

   Reached via the real link api/send-password-reset-email.php emails
   (generated server-side with the Kreait Admin SDK and built to point
   straight here, instead of to Firebase's own default hosted page) —
   the URL carries a real ?oobCode=... query param this file verifies,
   then uses to actually change the password, both straight against
   Firebase Auth:
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
    const requestNewLink = document.getElementById('rpRequestNewLink');
    const form = document.getElementById('resetPasswordForm');
    const formState = document.getElementById('rpFormState');
    const successState = document.getElementById('rpSuccessState');
    const newPasswordInput = document.getElementById('rpNewPassword');
    const confirmPasswordInput = document.getElementById('rpConfirmPassword');
    const submitBtn = document.getElementById('rpSubmitBtn');
    const strengthBar = document.getElementById('rpStrengthBar');
    const strengthLabel = document.getElementById('rpStrengthLabel');
    const reqLength = document.getElementById('rpReqLength');
    const reqMatch = document.getElementById('rpReqMatch');

    function showError(message, { offerNewLink = false } = {}) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        requestNewLink.classList.toggle('d-none', !offerNewLink);
    }

    /* ---------- Show/Hide password toggles (same pattern as login.php) ---------- */
    function wireToggle(inputId, btnId) {
        const input = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        if (!input || !btn) return;
        btn.addEventListener('click', function () {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            }
        });
    }
    wireToggle('rpNewPassword', 'toggleRpNewPassword');
    wireToggle('rpConfirmPassword', 'toggleRpConfirmPassword');

    /* ---------- Strength meter + requirements checklist ----------
       UI-only feedback — the real, enforced rule stays the 6-character
       minimum already on the input (minlength="6") and the submit-time
       checks below; this never blocks submission on its own. */
    function passwordStrength(password) {
        if (password.length === 0) return 0;
        let score = 0;
        if (password.length >= 6) score++;
        if (password.length >= 10) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password) && /[^A-Za-z0-9]/.test(password)) score++;
        return Math.min(score, 3);
    }
    function updateChecklist() {
        const password = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        const score = passwordStrength(password);
        strengthBar.classList.remove('is-weak', 'is-fair', 'is-strong');
        if (password.length === 0) {
            strengthLabel.textContent = '';
        } else if (score <= 1) {
            strengthBar.classList.add('is-weak');
            strengthLabel.textContent = 'Weak';
        } else if (score === 2) {
            strengthBar.classList.add('is-fair');
            strengthLabel.textContent = 'Fair';
        } else {
            strengthBar.classList.add('is-strong');
            strengthLabel.textContent = 'Strong';
        }

        setRequirementMet(reqLength, password.length >= 6);
        setRequirementMet(reqMatch, password.length > 0 && password === confirmPassword);
    }
    function setRequirementMet(li, isMet) {
        li.classList.toggle('is-met', isMet);
        const icon = li.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-circle', !isMet);
            icon.classList.toggle('bi-check-circle-fill', isMet);
        }
    }
    newPasswordInput.addEventListener('input', updateChecklist);
    confirmPasswordInput.addEventListener('input', updateChecklist);

    const params = new URLSearchParams(window.location.search);
    const oobCode = params.get('oobCode') || '';

    // Same generic "invalid or expired" wording regardless of the real
    // reason (missing code, already-used code, genuinely expired code)
    // — never reveals which specific thing was wrong, same principle
    // every other real link-validation flow in this app already follows.
    const INVALID_LINK_MESSAGE = 'This password reset link is invalid or has expired.';

    if (!oobCode) {
        subtitle.textContent = '';
        showError(INVALID_LINK_MESSAGE, { offerNewLink: true });
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
            showError(INVALID_LINK_MESSAGE, { offerNewLink: true });
        });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.classList.add('d-none');
        requestNewLink.classList.add('d-none');

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
                    form.classList.add('d-none');
                    showError(INVALID_LINK_MESSAGE, { offerNewLink: true });
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
