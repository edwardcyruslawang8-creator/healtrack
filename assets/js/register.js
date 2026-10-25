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
   via a real Firebase Auth popup, then collects a password through its
   own #googlePasswordModal — see that section below — before
   submitting through this SAME submit handler/api/register.php request
   either way, just with googleIdToken also set).
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
    const termsCheckbox = document.getElementById('regTerms');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        successBox.classList.add('d-none');
    }
    function hideMessages() {
        errorBox.classList.add('d-none');
        successBox.classList.add('d-none');
    }

    /* ---------- Terms of Service / Privacy Policy gate ----------
       Forces the Terms modal open (rather than just leaving the
       checkbox unexamined) the moment Create Account or Sign up with
       Google is clicked without #regTerms checked yet — see
       register.php's header comment above these modals. Each modal's
       own "I Agree" button checks the one #regTerms checkbox and
       closes the modal; the user then clicks Create Account/Google
       again to actually proceed. */
    const termsModalEl = document.getElementById('termsModal');
    function showTermsModal() {
        if (termsModalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(termsModalEl).show();
        }
    }
    function agreeAndCloseModal(modalEl) {
        termsCheckbox.checked = true;
        if (modalEl && typeof bootstrap !== 'undefined') {
            const instance = bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
    }
    const termsAgreeBtn = document.getElementById('termsAgreeBtn');
    if (termsAgreeBtn) {
        termsAgreeBtn.addEventListener('click', function () { agreeAndCloseModal(termsModalEl); });
    }
    const privacyAgreeBtn = document.getElementById('privacyAgreeBtn');
    if (privacyAgreeBtn) {
        privacyAgreeBtn.addEventListener('click', function () { agreeAndCloseModal(document.getElementById('privacyModal')); });
    }

    /* ---------- "Sign up with Google" ----------
       Only verifies identity (a real Firebase Auth popup) — account
       creation is still the SAME api/register.php request the manual
       form below submits (see register.php's header comment). Once it
       succeeds: Name is prefilled (still editable — Google's
       displayName isn't always split into first/last cleanly), Email
       is prefilled AND locked (it's now tied to the authenticated
       Google account), and the Password + Role fields are hidden
       entirely — this path collects both through its own dedicated
       #googlePasswordModal instead (opened right below), since Google
       only verifies WHO you are: it doesn't hand over a password, and
       never provides a role either. api/register.php verifies
       googleIdToken server-side itself and never trusts a
       client-supplied uid/email for that path. */
    const googleBtn = document.getElementById('googleSignUpBtn');
    const googleBackBtn = document.getElementById('googleSignUpBackBtn');
    const passwordField = document.getElementById('regPasswordField');
    const confirmPasswordField = document.getElementById('regConfirmPasswordField');
    const roleField = document.getElementById('regRoleField');
    const emailInput = document.getElementById('regEmail');
    const phoneOptionalNote = document.getElementById('regPhoneOptionalNote');
    let googleIdToken = null;

    /* ---------- "Create Your Password" modal (Google path only) ----------
       Its own three fields (#gpmPassword/#gpmConfirmPassword/#gpmRole),
       separate from the main form's #regPassword/#regConfirmPassword/
       #regRole — confirming here validates them the same way the manual
       path's submit handler does, then copies them into those main-form
       inputs and closes the modal, so the real submit handler below needs
       no Google-specific branching at all; it just sees those inputs
       already filled in and submits right away. */
    const googlePasswordModalEl = document.getElementById('googlePasswordModal');
    const googlePasswordModalError = document.getElementById('googlePasswordModalError');
    const gpmPassword = document.getElementById('gpmPassword');
    const gpmConfirmPassword = document.getElementById('gpmConfirmPassword');
    const gpmRole = document.getElementById('gpmRole');
    const googlePasswordModalSaveBtn = document.getElementById('googlePasswordModalSaveBtn');

    function showGooglePasswordModal() {
        if (googlePasswordModalError) googlePasswordModalError.classList.add('d-none');
        if (googlePasswordModalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(googlePasswordModalEl).show();
        }
    }

    if (googlePasswordModalSaveBtn) {
        googlePasswordModalSaveBtn.addEventListener('click', function () {
            const pwd = gpmPassword.value;
            const confirmPwd = gpmConfirmPassword.value;
            const roleValue = gpmRole.value;

            function showModalError(message) {
                if (!googlePasswordModalError) return;
                googlePasswordModalError.textContent = message;
                googlePasswordModalError.classList.remove('d-none');
            }

            if (!pwd || !confirmPwd || !roleValue) {
                showModalError('Please fill out every field.');
                return;
            }
            if (pwd.length < 6) {
                showModalError('Password must be at least 6 characters.');
                return;
            }
            if (pwd !== confirmPwd) {
                showModalError('Passwords do not match.');
                return;
            }

            document.getElementById('regPassword').value = pwd;
            document.getElementById('regConfirmPassword').value = confirmPwd;
            document.getElementById('regRole').value = roleValue;

            if (googlePasswordModalEl && typeof bootstrap !== 'undefined') {
                const instance = bootstrap.Modal.getInstance(googlePasswordModalEl);
                if (instance) instance.hide();
            }

            // Google's displayName doesn't always split into First/Last Name
            // cleanly — a single-word Google display name (e.g. just
            // "chesckamhae", no space) leaves Last Name blank, which used to
            // surface as a confusing generic "Please fill out every field"
            // error after this modal had already been completed. Catch it
            // here instead, right as the modal closes, and point at exactly
            // which Name field (still editable on the main form underneath)
            // still needs typing.
            const firstNameVal = document.getElementById('regFirstName').value.trim();
            const lastNameVal = document.getElementById('regLastName').value.trim();
            if (!firstNameVal || !lastNameVal) {
                const missingField = !firstNameVal ? 'First Name' : 'Last Name';
                showError('Google didn’t provide your full name. Please fill in ' + missingField + ' above, then click "Create Account" to finish.');
                document.getElementById(!firstNameVal ? 'regFirstName' : 'regLastName').focus();
                return;
            }

            // Password, Role, and Name are all set now — finish registration
            // right away instead of leaving it to a separate "Create
            // Account" click.
            form.requestSubmit();
        });
    }

    if (googleBtn && typeof firebase !== 'undefined') {
        const googleBtnDefaultHtml = googleBtn.innerHTML;
        googleBtn.addEventListener('click', function () {
            hideMessages();
            if (!termsCheckbox.checked) {
                showTermsModal();
                return;
            }

            // Already signed in with Google (just hadn't finished the
            // password modal yet, or wants to change what was typed) —
            // reopen the modal instead of launching a whole new popup.
            if (googleIdToken) {
                showGooglePasswordModal();
                return;
            }

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
                        if (roleField) roleField.classList.add('d-none');
                        // Re-enabled (unlike before the password modal existed) so
                        // clicking it again re-opens that modal instead of being a
                        // dead button — see the "already signed in" check above.
                        googleBtn.disabled = false;
                        googleBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Signed in as ' + (result.user.email || 'Google account');
                        if (googleBackBtn) googleBackBtn.classList.remove('d-none');

                        // Google doesn't hand over a phone number, so it's no longer
                        // required on this path — this is the one field that's simply
                        // skipped rather than collected elsewhere (added later from
                        // Profile Settings instead, nudged by
                        // includes/shared/phone_reminder.php). Mark the field optional
                        // and drop it from the "still needed" check below.
                        if (phoneOptionalNote) phoneOptionalNote.classList.remove('d-none');

                        gpmPassword.value = '';
                        gpmConfirmPassword.value = '';
                        gpmRole.value = '';
                        showGooglePasswordModal();
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
        // there), unlocks Email, restores the Password + Role fields, and
        // puts the Google button back to its default clickable state, so
        // someone who signed in with the wrong Google account (or just
        // changes their mind) can go back to typing the form manually
        // instead of being stuck with a locked email.
        if (googleBackBtn) {
            googleBackBtn.addEventListener('click', function () {
                hideMessages();
                googleIdToken = null;
                document.getElementById('regFirstName').value = '';
                document.getElementById('regLastName').value = '';
                document.getElementById('regPassword').value = '';
                document.getElementById('regConfirmPassword').value = '';
                document.getElementById('regRole').value = '';
                emailInput.value = '';
                emailInput.readOnly = false;
                passwordField.classList.remove('d-none');
                confirmPasswordField.classList.remove('d-none');
                if (roleField) roleField.classList.remove('d-none');
                if (phoneOptionalNote) phoneOptionalNote.classList.add('d-none');
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

        // Signed in with Google but never finished (or cancelled) the
        // password modal — #regPassword/#regRole are hidden on this path,
        // so a generic "fill out every field" error would point at fields
        // that aren't even visible. Reopen the modal instead.
        if (googleIdToken && (!password || !role)) {
            showGooglePasswordModal();
            return;
        }

        // Specific, not generic — "Please fill out every field" when only
        // one thing is actually missing (e.g. Google's displayName didn't
        // split into a Last Name) leaves no clue which one to fix.
        const missingFields = [];
        if (!firstName) missingFields.push('First Name');
        if (!lastName) missingFields.push('Last Name');
        if (!email) missingFields.push('Email Address');
        if (!googleIdToken && !phone) missingFields.push('Phone Number');
        if (!role) missingFields.push('Role');
        if (!password) missingFields.push('Password');
        if (!confirmPassword) missingFields.push('Confirm Password');
        if (missingFields.length > 0) {
            showError('Please fill out: ' + missingFields.join(', ') + '.');
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
        if (!termsAccepted) {
            showError('Please agree to the Terms of Service and Privacy Policy to continue.');
            showTermsModal();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating account...';

        const payload = { firstName: firstName, lastName: lastName, email: email, phone: phone, role: role, password: password, confirmPassword: confirmPassword, termsAccepted: termsAccepted };
        if (googleIdToken) {
            payload.googleIdToken = googleIdToken;
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
