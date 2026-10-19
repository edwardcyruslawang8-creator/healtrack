/* =============================================================
   REGISTRATION PAGE JS (register.php)
   ---------------------------------------------------------------
   Client-side validation (matching passwords, terms checkbox) then a
   plain POST to api/register.php — account creation happens entirely
   server-side (Firebase Admin SDK, same pattern as
   api/create-subadmin.php), so a new pending account is never signed
   in anywhere while awaiting approval. See register.php's header
   comment for the full pending-approval security model, and for how
   "Sign up with Google" fits into it (verifies identity client-side
   via a real Firebase Auth popup, then submits through this SAME
   submit handler/api/register.php request either way — just with
   googleIdToken set instead of a password).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Password show/hide toggles (both fields) ---------- */
    document.querySelectorAll('.reg-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            }
        });
    });

    /* ---------- Language selector (label only, matches login.php) ---------- */
    const langLabel = document.getElementById('langLabel');
    document.querySelectorAll('.lang-option').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            if (langLabel) langLabel.textContent = option.textContent.trim();
        });
    });

    /* ---------- Registration submit ---------- */
    const form = document.getElementById('registerForm');
    const errorBox = document.getElementById('regError');
    const successBox = document.getElementById('regSuccess');
    const submitBtn = document.getElementById('registerBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    const BASE_URL = window.APP_BASE_URL || '';

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        successBox.classList.add('d-none');
    }
    function hideMessages() {
        errorBox.classList.add('d-none');
        successBox.classList.add('d-none');
    }

    /* ---------- "Sign up with Google" ----------
       Only verifies identity (a real Firebase Auth popup) — account
       creation is still the SAME api/register.php request the manual
       form below submits (see register.php's header comment). Once it
       succeeds: Name is prefilled (still editable — Google's
       displayName isn't always split into first/last cleanly), Email
       is prefilled AND locked (it's now tied to the authenticated
       Google account), and the Password fields are hidden entirely —
       api/register.php verifies googleIdToken server-side itself and
       never trusts a client-supplied uid/email for that path. */
    const googleBtn = document.getElementById('googleSignUpBtn');
    const googleBackBtn = document.getElementById('googleSignUpBackBtn');
    const passwordField = document.getElementById('regPasswordField');
    const confirmPasswordField = document.getElementById('regConfirmPasswordField');
    const emailInput = document.getElementById('regEmail');
    let googleIdToken = null;

    if (googleBtn && typeof firebase !== 'undefined') {
        const googleBtnDefaultHtml = googleBtn.innerHTML;
        googleBtn.addEventListener('click', function () {
            hideMessages();
            googleBtn.disabled = true;
            googleBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Signing in...';

            firebase.auth().signInWithPopup(new firebase.auth.GoogleAuthProvider())
                .then(function (result) {
                    return result.user.getIdToken().then(function (idToken) {
                        googleIdToken = idToken;

                        const nameParts = (result.user.displayName || '').trim().split(/\s+/);
                        document.getElementById('regFirstName').value = nameParts[0] || '';
                        document.getElementById('regLastName').value = nameParts.slice(1).join(' ');
                        emailInput.value = result.user.email || '';
                        emailInput.readOnly = true;

                        passwordField.classList.add('d-none');
                        confirmPasswordField.classList.add('d-none');
                        googleBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Signed in as ' + (result.user.email || 'Google account');
                        if (googleBackBtn) googleBackBtn.classList.remove('d-none');
                    });
                })
                .catch(function (error) {
                    googleBtn.disabled = false;
                    googleBtn.innerHTML = googleBtnDefaultHtml;
                    if (error.code === 'auth/popup-closed-by-user' || error.code === 'auth/cancelled-popup-request') return;
                    showError('Something went wrong signing in with Google. Please try again.');
                });
        });

        // Undoes a successful Google sign-in — clears googleIdToken (so
        // submit falls back to the manual password path), clears every
        // field Google auto-filled (Name too, not just Email — a real
        // clean form to type into, not Google's values left sitting
        // there), unlocks Email, restores the Password fields, and puts
        // the Google button back the way it started.
        if (googleBackBtn) {
            googleBackBtn.addEventListener('click', function () {
                hideMessages();
                googleIdToken = null;
                document.getElementById('regFirstName').value = '';
                document.getElementById('regLastName').value = '';
                emailInput.value = '';
                emailInput.readOnly = false;
                passwordField.classList.remove('d-none');
                confirmPasswordField.classList.remove('d-none');
                googleBtn.disabled = false;
                googleBtn.innerHTML = googleBtnDefaultHtml;
                googleBackBtn.classList.add('d-none');
            });
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideMessages();

        const firstName = document.getElementById('regFirstName').value.trim();
        const lastName = document.getElementById('regLastName').value.trim();
        const email = emailInput.value.trim();
        const phone = document.getElementById('regPhone').value.trim();
        const password = document.getElementById('regPassword').value;
        const confirmPassword = document.getElementById('regConfirmPassword').value;
        const role = document.getElementById('regRole').value;
        const termsAccepted = document.getElementById('regTerms').checked;

        if (!firstName || !lastName || !email || !phone || !role) {
            showError('Please fill out every field.');
            return;
        }
        if (!googleIdToken) {
            if (!password || !confirmPassword) {
                showError('Please fill out every field.');
                return;
            }
            if (password.length < 6) {
                showError('Password must be at least 6 characters.');
                return;
            }
            if (password !== confirmPassword) {
                showError('Passwords do not match.');
                return;
            }
        }
        if (!termsAccepted) {
            showError('Please agree to the Terms of Service and Privacy Policy to continue.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating account...';

        const payload = { firstName: firstName, lastName: lastName, email: email, phone: phone, role: role, termsAccepted: termsAccepted };
        if (googleIdToken) {
            payload.googleIdToken = googleIdToken;
        } else {
            payload.password = password;
            payload.confirmPassword = confirmPassword;
        }

        fetch(BASE_URL + '/api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                form.reset();
                form.classList.add('d-none');
                if (googleBtn) googleBtn.classList.add('d-none');
                successBox.textContent = 'Account request submitted! A sub-administrator will review it before you can sign in. You’ll be able to sign in as soon as it’s approved.';
                successBox.classList.remove('d-none');
            })
            .catch(function (err) {
                showError(err.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
