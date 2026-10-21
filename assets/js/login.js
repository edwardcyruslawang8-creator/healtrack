/* =============================================================
   LOGIN PAGE JS (login.php)
   ---------------------------------------------------------------
   Loaded only by login.php, after firebase-app-compat.js,
   firebase-auth-compat.js, firebase-firestore-compat.js, and
   firebase-config.js. Handles the password show/hide toggle, the
   language selector label, Firebase Authentication (email/password)
   sign-in, and role-based redirect (see routeByRole() below).

   SETUP NOTE: for someone to land on the admin dashboard, their
   Firebase Auth account needs a matching Firestore document at
   users/{uid} with a field role: "admin". Without that document (or
   with an unrecognized role), sign-in succeeds but they'll see a
   message instead of being redirected — that's intentional, so no
   one silently lands on the wrong (or a broken) page.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Password show/hide toggle ---------- */
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassword');

    if (passwordInput && toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';

            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isHidden);
                icon.classList.toggle('bi-eye-slash', isHidden);
            }
        });
    }

    /* ---------- Language selector ---------- */
    const langLabel = document.getElementById('langLabel');
    document.querySelectorAll('.lang-option').forEach(function (option) {
        option.addEventListener('click', function (e) {
            e.preventDefault();
            if (langLabel) {
                langLabel.textContent = option.textContent.trim();
            }
            // TODO: once multi-language support exists, store the choice
            // (e.g. in a cookie or session) and reload strings accordingly.
        });
    });

    /* ---------- Firebase Authentication: sign in ---------- */
    const loginForm      = document.getElementById('loginForm');
    const usernameInput  = document.getElementById('username');
    const rememberMeBox  = document.getElementById('rememberMe');
    const errorBox       = document.getElementById('loginError');
    const signInBtn      = document.getElementById('signInBtn');
    const googleBtn      = document.getElementById('googleSignInBtn');
    const BASE_URL       = window.APP_BASE_URL || '';

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }
    function hideError() {
        if (!errorBox) return;
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }
    function setLoading(isLoading) {
        if (!signInBtn) return;
        signInBtn.disabled = isLoading;
        signInBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm"></span> Signing in...'
            : '<i class="bi bi-lock-fill"></i> Sign In';
        if (googleBtn) googleBtn.disabled = isLoading;
    }
    const googleBtnDefaultHtml = googleBtn ? googleBtn.innerHTML : '';
    function setGoogleLoading(isLoading) {
        if (!googleBtn) return;
        googleBtn.disabled = isLoading;
        if (signInBtn) signInBtn.disabled = isLoading;
        googleBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm"></span> Signing in...'
            : googleBtnDefaultHtml;
    }

    // Firebase's own error codes, translated into friendlier text.
    function friendlyAuthError(error) {
        switch (error.code) {
            case 'auth/invalid-email':
                return 'That email address doesn\'t look right.';
            case 'auth/user-not-found':
            case 'auth/wrong-password':
            case 'auth/invalid-credential':
                return 'Incorrect email or password. Please try again.';
            case 'auth/too-many-requests':
                return 'Too many attempts. Please wait a moment and try again.';
            case 'auth/network-request-failed':
                return 'Network error — please check your connection and try again.';
            default:
                return 'Something went wrong signing you in. Please try again.';
        }
    }

    /**
     * ---------- Login lockout (server-enforced, see includes/shared/login_lockout.php) ----------
     * Firebase Auth verifies passwords entirely client-side, so a WRONG
     * password never reaches the PHP server on its own — checkLockoutBeforeSubmit()
     * asks BEFORE attempting sign-in (so a locked-out person never wastes
     * a Firebase attempt), and reportFailedLogin() is how the server
     * ever learns an attempt failed at all (called from the sign-in
     * .catch() below). api/session-login.php re-checks this again after
     * a real password is verified — that's the actual unbypassable
     * backstop; this client-side layer is just the fast, friendly UI.
     */
    let lockoutCountdownTimer = null;

    function clearLockoutCountdown() {
        if (lockoutCountdownTimer) {
            clearInterval(lockoutCountdownTimer);
            lockoutCountdownTimer = null;
        }
    }

    /** Shows/disables the UI for a {locked, permanentlyLocked, retryAfterSeconds, message} result. Returns true if currently locked. */
    function applyLockout(info) {
        clearLockoutCountdown();
        if (!info || !info.locked) {
            if (signInBtn) signInBtn.disabled = false;
            return false;
        }

        showError(info.message || 'Too many failed attempts. Please try again later.');
        if (signInBtn) signInBtn.disabled = true;

        if (!info.permanentlyLocked && info.retryAfterSeconds > 0) {
            let remaining = info.retryAfterSeconds;
            lockoutCountdownTimer = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    clearLockoutCountdown();
                    hideError();
                    if (signInBtn) signInBtn.disabled = false;
                    return;
                }
                showError('Too many incorrect attempts. Please try again in ' + remaining + ' second' + (remaining === 1 ? '' : 's') + '.');
            }, 1000);
        }

        return true;
    }

    function checkLockoutBeforeSubmit(email) {
        return fetch(BASE_URL + '/api/check-login-lockout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) { return applyLockout(data); })
            .catch(function () { return false; }); // a network hiccup here shouldn't block signing in — api/session-login.php still re-checks server-side either way
    }

    function reportFailedLogin(email) {
        return fetch(BASE_URL + '/api/report-failed-login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email }),
        })
            .then(function (res) { return res.json(); })
            .catch(function () { return null; });
    }

    /**
     * Shared by the email/password submit handler and the Google
     * handler below — api/session-login.php can resolve 3 ways once
     * Firebase Auth itself has already succeeded: a real session
     * starts (routeByRole), the account isn't active yet (sign back
     * out, show why), or twoFactorEnabled is true and NEITHER of those
     * happened — $_SESSION['pending2faUid'] is set server-side instead,
     * so the code-entry step (#loginStep2fa) needs to appear. The later,
     * separate "Verify" click (api/verify-2fa.php) gets its own `role`
     * straight from THAT endpoint's response once a correct code comes
     * back, so nothing about the original sign-in needs to be held onto
     * across that gap.
     */
    const loginStepCredentials = document.getElementById('loginStepCredentials');
    const loginStep2fa = document.getElementById('loginStep2fa');

    function showTwoFaStep() {
        if (!loginStepCredentials || !loginStep2fa) return;
        loginStepCredentials.classList.add('d-none');
        loginStep2fa.classList.remove('d-none');
        const codeInput = document.getElementById('twoFaLoginCode');
        if (codeInput) {
            codeInput.value = '';
            codeInput.focus();
        }
    }
    function showCredentialsStep() {
        if (!loginStepCredentials || !loginStep2fa) return;
        loginStep2fa.classList.add('d-none');
        loginStepCredentials.classList.remove('d-none');
    }

    function handleSessionResult(sessionResult, setLoadingFn) {
        if (sessionResult.requires2fa) {
            setLoadingFn(false);
            showTwoFaStep();
            return null;
        }
        if (sessionResult.requiresPasswordSetup) {
            // A "Sign up with Google" account with no password yet —
            // api/session-login.php already stashed the pending uid
            // server-side (same pattern as 2FA above), so this is just a
            // redirect, not another API call.
            window.location.href = BASE_URL + '/set-password.php';
            return null;
        }
        if (!sessionResult.success) {
            // Firebase Auth succeeded (correct credential), but the
            // account isn't active yet (pending approval / locked) —
            // api/session-login.php refused to start a real app session,
            // so there's nothing to route to. Sign back out client-side
            // too, rather than leaving the browser Firebase-authenticated
            // with nowhere to go.
            return firebase.auth().signOut().then(function () {
                setLoadingFn(false);
                showError(sessionResult.message || 'This account is not active yet. Please contact your system administrator.');
            });
        }
        return routeByRole(sessionResult.role);
    }

    if (loginForm && typeof firebase !== 'undefined') {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            hideError();

            const email = usernameInput.value.trim();
            const password = passwordInput.value;
            const remember = rememberMeBox ? rememberMeBox.checked : false;

            if (!email || !password) {
                showError('Please enter both your email and password.');
                return;
            }

            setLoading(true);

            checkLockoutBeforeSubmit(email).then(function (isLocked) {
                if (isLocked) {
                    setLoading(false);
                    return;
                }

                // "Remember me" → keep the session across browser restarts (LOCAL);
                // otherwise it clears when the browser/tab closes (SESSION).
                const persistence = remember
                    ? firebase.auth.Auth.Persistence.LOCAL
                    : firebase.auth.Auth.Persistence.SESSION;

                firebase.auth().setPersistence(persistence)
                    .then(function () {
                        return firebase.auth().signInWithEmailAndPassword(email, password);
                    })
                    .then(function (credential) {
                        return startServerSession(credential.user, remember).then(function (sessionResult) {
                            return handleSessionResult(sessionResult, setLoading);
                        });
                    })
                    .catch(function (error) {
                        // A wrong password is the one failure Firebase Auth never
                        // tells this app's own server about on its own — report it
                        // for real so the escalating lockout (1 min at 3 attempts,
                        // 3 min + notifications at 5, 5 min at 6, permanent at 7)
                        // actually counts this one.
                        const isWrongPassword = error.code === 'auth/wrong-password'
                            || error.code === 'auth/invalid-credential'
                            || error.code === 'auth/user-not-found';

                        if (isWrongPassword) {
                            reportFailedLogin(email).then(function (info) {
                                setLoading(false);
                                if (!applyLockout(info)) {
                                    showError(friendlyAuthError(error));
                                }
                            });
                            return;
                        }

                        setLoading(false);
                        showError(friendlyAuthError(error));
                    });
            });
        });
    } else if (loginForm) {
        // Firebase never became available — one of the 3 gstatic.com SDK
        // script tags in login.php failed to load (a blocked/flaky
        // connection to Google's CDN, an ad-blocker, etc.). A failed
        // <script src> load is silent (no page error), so without this the
        // user would click "Sign In" with no listener ever attached and
        // fall through to this form's own action/method — which can never
        // complete a real login anyway (api/session-login.php requires a
        // verified Firebase idToken only the client-side SDK can produce)
        // and used to point at a route that didn't even exist
        // (api/login.php), surfacing as a raw, unexplained 404. This gives
        // the user an actual reason instead.
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            showError('Unable to load required sign-in scripts. Please check your internet connection and reload the page.');
        });
    }

    /* ---------- 2FA code entry (#loginStep2fa) ---------- */
    const twoFaSubmitBtn = document.getElementById('twoFaLoginSubmitBtn');
    const twoFaCodeInput = document.getElementById('twoFaLoginCode');
    const twoFaErrorBox = document.getElementById('twoFaLoginError');
    const twoFaBackLink = document.getElementById('twoFaLoginBackLink');

    if (twoFaSubmitBtn) {
        const twoFaSubmitDefaultHtml = twoFaSubmitBtn.innerHTML;
        twoFaSubmitBtn.addEventListener('click', function () {
            const code = twoFaCodeInput.value.trim();
            if (!code) {
                twoFaErrorBox.textContent = 'Please enter your 6-digit code.';
                twoFaErrorBox.classList.remove('d-none');
                return;
            }
            twoFaErrorBox.classList.add('d-none');
            twoFaSubmitBtn.disabled = true;
            twoFaSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';

            fetch(BASE_URL + '/api/verify-2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code }),
            })
                .then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'That code is incorrect or has expired. Please try again.');
                    }
                    return routeByRole(result.data.role);
                })
                .catch(function (err) {
                    twoFaErrorBox.textContent = err.message;
                    twoFaErrorBox.classList.remove('d-none');
                    twoFaSubmitBtn.disabled = false;
                    twoFaSubmitBtn.innerHTML = twoFaSubmitDefaultHtml;
                });
        });
    }

    if (twoFaBackLink) {
        twoFaBackLink.addEventListener('click', function (e) {
            e.preventDefault();
            firebase.auth().signOut().then(showCredentialsStep);
        });
    }

    /**
     * "Continue with Google" — reuses startServerSession()/routeByRole()
     * UNCHANGED, so it behaves exactly like the email/password flow
     * once Firebase Auth succeeds: api/session-login.php only starts a
     * real app session for an EXISTING users/{uid} doc with
     * status="active" — this button signs a person IN, it never creates
     * an account (register.php's Google button handles account
     * creation — see assets/js/register.js).
     */
    if (googleBtn && typeof firebase !== 'undefined') {
        googleBtn.addEventListener('click', function () {
            hideError();
            setGoogleLoading(true);

            // Same "Remember me" checkbox the email/password flow reads —
            // it's on the same form, so it should mean the same thing
            // regardless of which sign-in method is used.
            const remember = rememberMeBox ? rememberMeBox.checked : false;
            const persistence = remember
                ? firebase.auth.Auth.Persistence.LOCAL
                : firebase.auth.Auth.Persistence.SESSION;

            const provider = new firebase.auth.GoogleAuthProvider();
            firebase.auth().setPersistence(persistence)
                .then(function () {
                    return firebase.auth().signInWithPopup(provider);
                })
                .then(function (result) {
                    return startServerSession(result.user, remember).then(function (sessionResult) {
                        return handleSessionResult(sessionResult, setGoogleLoading);
                    });
                })
                .catch(function (error) {
                    setGoogleLoading(false);
                    // Not a real error — the person just closed the popup or
                    // clicked the button twice; nothing to show.
                    if (error.code === 'auth/popup-closed-by-user' || error.code === 'auth/cancelled-popup-request') return;
                    if (error.code === 'auth/popup-blocked') {
                        showError('Your browser blocked the Google sign-in popup. Please allow popups for this site and try again.');
                        return;
                    }
                    if (error.code === 'auth/account-exists-with-different-credential') {
                        showError('An account already exists with this email using a different sign-in method.');
                        return;
                    }
                    showError(friendlyAuthError(error));
                });
        });
    }

    /**
     * Verifies this sign-in with the PHP backend (api/session-login.php)
     * so server-side pages know who's logged in, not just client-side JS
     * — and, critically, so a "pending"/"locked" account (see that
     * file's header comment) can't reach a dashboard just because
     * Firebase Auth itself accepted the password. Resolves to
     * {success, message, requires2fa} either way; only a real network
     * failure rejects the promise.
     */
    function startServerSession(user, remember) {
        return user.getIdToken()
            .then(function (idToken) {
                return fetch(BASE_URL + '/api/session-login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    // `remember` also decides how long the actual PHP app
                    // session cookie lasts (api/session-login.php) — the
                    // firebase.auth().setPersistence() call above only
                    // covers the client-side Firebase SDK's own credential
                    // cache, not the server session every dashboard page
                    // actually gates on, so both need this same flag.
                    body: JSON.stringify({ idToken: idToken, remember: !!remember }),
                });
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    // requires2fa was previously dropped here — a
                    // twoFactorEnabled account's session-login.php response
                    // (success:true, requires2fa:true, no real session
                    // started) looked identical to a normal successful
                    // login by the time handleSessionResult() saw it, so
                    // it went straight to routeByRole() instead of the
                    // code-entry step, skipping 2FA entirely. Same
                    // reasoning for requiresPasswordSetup below.
                    //
                    // `role` is api/session-login.php's own real read of
                    // users/{uid} (Admin SDK — not subject to Firestore
                    // security rules) — routeByRole() uses THIS instead of
                    // a second, redundant client-side Firestore read of
                    // the same document. That client-side read used to be
                    // the only way the browser learned the role, but it
                    // depends on Firestore security rules permitting it,
                    // which isn't guaranteed to hold for every account/
                    // browser the same way — a real case of this: a
                    // teammate signing in with a real, correct password
                    // for a real active account got "no role set up" purely
                    // because that second read came back empty, even
                    // though the server had already confirmed the role
                    // and started the session.
                    return {
                        success: !!data.success,
                        message: data.message,
                        requires2fa: !!data.requires2fa,
                        requiresPasswordSetup: !!data.requiresPasswordSetup,
                        role: data.role || null,
                    };
                });
            });
    }

    /**
     * Redirects to the dashboard matching $role — $role comes from
     * api/session-login.php's (or api/verify-2fa.php's) own JSON
     * response, a real Admin-SDK read of users/{uid}/role already
     * performed server-side to decide whether to start this session at
     * all. No separate client-side Firestore read here — see
     * startServerSession()'s comment for why that used to exist and why
     * it was removed (a Firestore-security-rules-dependent read that
     * could come back empty for a real, correctly-authenticated account).
     *
     *   - "admin"           → admin/admin_dashboard.php (built)
     *   - "subadmin"        → subadmin/dashboard.php (built)
     *   - "healthcare_staff" → medical-staff/dashboard.php (built)
     *   - "pharmacist"      → pharmacist/dashboard.php (built)
     *   - "medical_technician" → medical_technician/dashboard.php (built)
     *   - "radio_technologist" → radiologic_technologist/dashboard.php (built)
     *   - "doctor"          → doctor/dashboard.php (built)
     *   - "patient" → no dedicated post-login destination exists yet
     *     (index.php is the public homepage, not a patient dashboard —
     *     it isn't gated behind login), so we tell the person instead
     *     of redirecting them there.
     *   - anything else (no role, or one not listed above) → same
     *     "not set up yet" message, rather than guessing.
     */
    function routeByRole(role) {
        switch (role) {
            case 'admin':
                window.location.href = BASE_URL + '/admin/admin_dashboard.php';
                break;
            case 'subadmin':
                window.location.href = BASE_URL + '/subadmin/dashboard.php';
                break;
            case 'healthcare_staff':
                window.location.href = BASE_URL + '/medical-staff/dashboard.php';
                break;
            case 'pharmacist':
                window.location.href = BASE_URL + '/pharmacist/dashboard.php';
                break;
            case 'medical_technician':
                window.location.href = BASE_URL + '/medical_technician/dashboard.php';
                break;
            case 'radio_technologist':
                window.location.href = BASE_URL + '/radiologic_technologist/dashboard.php';
                break;
            case 'doctor':
                window.location.href = BASE_URL + '/doctor/dashboard.php';
                break;
            case 'patient':
                setLoading(false);
                showCredentialsStep(); // in case this ran after a successful 2FA step, whose error box isn't visible
                showError('Signed in successfully, but the dashboard for your role isn\'t built yet. Please check back soon.');
                break;
            default:
                setLoading(false);
                showCredentialsStep();
                showError('Signed in, but no role is set up for this account yet. Please contact the system administrator.');
        }
    }

});

