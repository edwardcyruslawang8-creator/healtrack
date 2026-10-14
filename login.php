<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Login Page (login.php)
 * ---------------------------------------------------------------------
 * This is the single shared sign-in page for every role in the system
 * (admin, sub-admin, healthcare staff, and patient) — none of those
 * role-specific dashboards have their own login UI, so they all route
 * through here.
 *
 * AUTHENTICATION: wired up to Firebase Authentication (email/password),
 * client-side, via assets/js/firebase-config.js + assets/js/login.js.
 * After sign-in, the user's role is looked up at Firestore doc
 * users/{uid} and they're redirected accordingly:
 *   - role "admin"    → admin/admin_dashboard.php (built)
 *   - role "subadmin" → subadmin/dashboard.php (built)
 *   - role "patient" / "healthcare_staff", or no users/{uid} doc at
 *     all → a message is shown instead of redirecting, since neither
 *     has a dashboard yet. Note index.php is NOT a patient dashboard —
 *     it's the public homepage, open without logging in, so it's never
 *     a login redirect target.
 *
 * See assets/js/login.js (routeByRole()) for exactly how the role is
 * checked and where each one goes. api/session-login.php also verifies
 * the ID token server-side and starts a PHP session from it, so PHP
 * pages (not just client-side JS) know who's signed in.
 *
 * Account creation: register.php is a public self-registration page
 * now (footer link below) — but signing up there does NOT grant
 * access by itself. Every self-registered account starts with
 * status="pending" until a sub-admin approves it in User Management's
 * "Pending Approvals" table (deciding on these requests is a
 * sub-admin capability, not admin's); api/session-login.php refuses to
 * start a session for a non-active account, which is what actually
 * blocks a pending account from reaching a dashboard here (not just a
 * message — see that file's header comment).
 *
 * STILL TODO before this is production-ready:
 *   1. Patient and healthcare-staff dashboards don't exist yet — once
 *      built, add their cases to routeByRole() in login.js.
 * "Forgot password?" below now links to a real forgot-password.php,
 * which uses firebase.auth().sendPasswordResetEmail() — see that
 * file's header comment.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/config/constants.php'; // BASE_URL

// Placeholder for a future flash message (e.g. "Invalid username or password").
$loginError = null;

// Real — reached via api/logout.php's own ?reason=idle passthrough,
// itself reached either by includes/shared/idle_timeout.php's
// server-side check or assets/js/idle-timeout.js's client-side timer
// (see either file's header comment). Not shown for a normal manual
// "Log out" click, which never sends this param.
$idleTimeoutNotice = ($_GET['reason'] ?? '') === 'idle'
    ? 'You were signed out after 30 minutes of inactivity, for your security. Please sign in again.'
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Base stylesheet (shared CSS variables/fonts only — this page doesn't use the dashboard sidebar) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<!-- Login page stylesheet -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/login.css') ?>">

<script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
</head>
<body>

<div class="login-page">

    <!-- =========================================================
         LEFT PANEL — brand / illustration
         ========================================================= -->
    <div class="login-left">

        <!-- Logo area -->
        <div class="login-logo-row">
            <div class="logo-badge"><i class="bi bi-shield-fill-plus"></i></div>
            <div class="login-brand-text">
                <h1>NORTH SUPER HEALTH CENTER</h1>
                <p>Healthcare Management System</p>
            </div>
        </div>

        <hr class="login-divider">

        <!-- Heading + description -->
        <h2>Better Health. Stronger Communities.</h2>
        <p class="login-left-desc">
            Empowering our barangays with accessible healthcare services, real-time medicine
            availability, and reliable health information — all in one place.
        </p>

        <!-- Illustration section -->
        <div class="login-illustration">
            <img src="<?= BASE_URL ?>/assets/images/bg.png" alt="Healthcare illustration">
        </div>

        <!-- Bottom tagline -->
        <div class="login-tagline">
            <i class="bi bi-shield-fill-check"></i>
            <span>Your partner in building a healthier tomorrow.</span>
        </div>
    </div>

    <!-- =========================================================
         RIGHT PANEL — login form
         ========================================================= -->
    <div class="login-right">

        <!-- Language selector -->
        <div class="lang-selector dropdown">
            <button class="btn-lang dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-globe2"></i> <span id="langLabel">English</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item lang-option" href="#">English</a></li>
                <li><a class="dropdown-item lang-option" href="#">Filipino</a></li>
            </ul>
        </div>

        <!-- Login card -->
        <div class="login-card">

        <!-- ============ STEP 1: email + password / Google ============ -->
        <div id="loginStepCredentials">
            <h2>Welcome back!</h2>
            <p class="login-card-subtitle">Sign in to your account to continue managing your healthcare system.</p>

            <?php if ($loginError): ?>
            <div class="alert alert-danger py-2 px-3" style="font-size:13px;"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <?php if ($idleTimeoutNotice): ?>
            <div class="alert alert-warning py-2 px-3" style="font-size:13px;"><i class="bi bi-clock-history me-1"></i><?= htmlspecialchars($idleTimeoutNotice) ?></div>
            <?php endif; ?>

            <!-- Client-side auth errors (Firebase) appear here -->
            <div id="loginError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

            <!-- The real sign-in happens entirely client-side via the Firebase
                 Auth SDK (assets/js/login.js), which always calls
                 e.preventDefault() before posting the resulting idToken to
                 api/session-login.php — this action/method are only ever hit
                 if that JS never attaches (e.g. the Firebase SDK scripts
                 below failed to load), so this points at a route that at
                 least exists rather than api/login.php (never existed,
                 produced a raw 404). It still can't complete a real login
                 on its own — session-login.php requires a verified Firebase
                 idToken, which only the client-side SDK can produce — see
                 the Firebase-availability check in login.js, which shows a
                 clear error instead of silently leaving the form like this. -->
            <form action="<?= BASE_URL ?>/api/session-login.php" method="post" autocomplete="off" id="loginForm">

                <!-- Email field (Firebase Authentication signs in with email + password) -->
                <div class="login-field">
                    <label for="username">Email</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope-fill field-icon"></i>
                        <input type="email" id="username" name="email" placeholder="Enter your email" required>
                    </div>
                </div>

                <!-- Password field -->
                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-lock-fill field-icon"></i>
                        <input type="password" id="password" name="password" class="has-toggle" placeholder="Enter your password" required>
                        <button type="button" class="login-toggle-btn" id="togglePassword" aria-label="Show or hide password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Forgot password -->
                <div class="login-forgot-row">
                    <a href="<?= BASE_URL ?>/forgot-password.php">Forgot password?</a>
                </div>

                <!-- Remember me -->
                <div class="login-remember">
                    <input type="checkbox" id="rememberMe" name="remember_me">
                    <label for="rememberMe">Remember me</label>
                </div>

                <!-- Sign In button -->
                <button type="submit" class="btn-sign-in" id="signInBtn">
                    <i class="bi bi-lock-fill"></i> Sign In
                </button>
            </form>

            <div class="login-or-divider">or</div>

            <!-- Google Sign-In — only works for an EXISTING account (same
                 users/{uid} lookup as the form above, via
                 api/session-login.php); it does not create one. Someone
                 signing in with Google for the first time (no matching
                 Firestore doc) sees the same "no account found" message
                 register.php's link right below points them at. -->
            <button type="button" class="btn-google-signin" id="googleSignInBtn">
                <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                </svg>
                Continue with Google
            </button>

            <hr class="login-card-divider">

            <!-- Footer -->
            <p class="login-footer-text">
                Don't have an account? <a href="<?= BASE_URL ?>/register.php">Create one here.</a>
            </p>
        </div>

        <!-- ============ STEP 2: 2FA code (only shown when
             api/session-login.php returns {requires2fa: true} —
             assets/js/login.js) ============ -->
        <div id="loginStep2fa" class="d-none">
            <h2>Enter your code</h2>
            <p class="login-card-subtitle">Open your authenticator app and enter the 6-digit code for your account.</p>

            <div id="twoFaLoginError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

            <div class="login-field">
                <label for="twoFaLoginCode">Authentication Code</label>
                <div class="login-input-wrap">
                    <i class="bi bi-shield-lock-fill field-icon"></i>
                    <input type="text" id="twoFaLoginCode" placeholder="000000" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                </div>
            </div>

            <button type="button" class="btn-sign-in" id="twoFaLoginSubmitBtn">
                <i class="bi bi-shield-lock-fill"></i> Verify
            </button>

            <p class="login-footer-text" style="margin-top:16px;">
                <a href="#" id="twoFaLoginBackLink"><i class="bi bi-arrow-left"></i> Back to sign in</a>
            </p>
        </div>

        </div>

    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Firebase (Authentication + Firestore for role lookup) — loaded only on this page -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= asset_url('/assets/js/firebase-config.js') ?>"></script>

<!-- Login page JS -->
<script src="<?= asset_url('/assets/js/login.js') ?>"></script>
</body>
</html>
