<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Forgot Password (forgot-password.php)
 * ---------------------------------------------------------------------
 * Linked from login.php's "Forgot password?" row — was a dead link
 * (BASE_URL . '/forgot-password.php', pointing at a page that didn't
 * exist) until now. Reuses login.php's exact left-panel branding and
 * assets/css/login.css's card/field styles verbatim — same shared
 * pre-login shell, no new CSS needed.
 *
 * No custom backend endpoint here: Firebase Authentication itself
 * sends the reset email and hosts the whole "enter a new password"
 * flow (assets/js/forgot-password.js calls
 * firebase.auth().sendPasswordResetEmail(email) client-side, same
 * Firebase project/config login.php already uses via
 * assets/js/firebase-config.js) — nothing in this app's own database
 * is touched, and no session is required to reach this page.
 *
 * Deliberately shows the SAME "check your email" message whether or
 * not the address actually matches an account (see
 * assets/js/forgot-password.js's error handling) — not showing
 * different outcomes for existing vs. non-existing emails avoids
 * letting this form be used to enumerate registered accounts.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/config/constants.php'; // BASE_URL
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Base stylesheet (shared CSS variables/fonts only — this page doesn't use the dashboard sidebar) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<!-- Reused verbatim from login.php — same card/field/left-panel styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/login.css') ?>">

<script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
</head>
<body>

<div class="login-page">

    <!-- =========================================================
         LEFT PANEL — brand / illustration (identical to login.php)
         ========================================================= -->
    <div class="login-left">

        <div class="login-logo-row">
            <div class="logo-badge"><i class="bi bi-shield-fill-plus"></i></div>
            <div class="login-brand-text">
                <h1>NORTH SUPER HEALTH CENTER</h1>
                <p>Healthcare Management System</p>
            </div>
        </div>

        <hr class="login-divider">

        <h2>Better Health. Stronger Communities.</h2>
        <p class="login-left-desc">
            Empowering our barangays with accessible healthcare services, real-time medicine
            availability, and reliable health information — all in one place.
        </p>

        <div class="login-illustration">
            <img src="<?= BASE_URL ?>/assets/images/bg.png" alt="Healthcare illustration">
        </div>

        <div class="login-tagline">
            <i class="bi bi-shield-fill-check"></i>
            <span>Your partner in building a healthier tomorrow.</span>
        </div>
    </div>

    <!-- =========================================================
         RIGHT PANEL — forgot-password form
         ========================================================= -->
    <div class="login-right">

        <div class="login-card">

            <!-- ===== Email reset-link mode (default) ===== -->
            <div id="fpEmailLinkMode">
                <h2>Forgot your password?</h2>
                <p class="login-card-subtitle">Enter the email address on your account and we'll send you a link to reset your password.</p>

                <!-- Client-side result (Firebase) appears here -->
                <div id="fpError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
                <div id="fpSuccess" class="alert alert-success py-2 px-3 d-none" style="font-size:13px;"></div>

                <form autocomplete="off" id="forgotPasswordForm">

                    <div class="login-field">
                        <label for="fpEmail">Email</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-envelope-fill field-icon"></i>
                            <input type="email" id="fpEmail" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-sign-in" id="fpSubmitBtn">
                        <i class="bi bi-send-fill"></i> Send Reset Link
                    </button>
                </form>

                <p class="login-footer-text mt-3">
                    <a href="#" id="showTotpModeLink"><i class="bi bi-shield-lock-fill"></i> Have 2FA set up? Reset using your authenticator app instead.</a>
                </p>
            </div>

            <!-- ===== Authenticator-code reset mode (alternative — doesn't
                 depend on a reset email ever arriving) ===== -->
            <div id="fpTotpMode" class="d-none">
                <h2>Reset with your authenticator code</h2>
                <p class="login-card-subtitle">If your account has two-factor authentication enabled, enter your email, your current 6-digit code, and a new password — no email needed.</p>

                <div id="trError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
                <div id="trSuccess" class="alert alert-success py-2 px-3 d-none" style="font-size:13px;"></div>

                <form autocomplete="off" id="totpResetForm">

                    <div class="login-field">
                        <label for="trEmail">Email</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-envelope-fill field-icon"></i>
                            <input type="email" id="trEmail" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="trCode">Authenticator Code</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-shield-lock-fill field-icon"></i>
                            <input type="text" id="trCode" name="code" placeholder="6-digit code" inputmode="numeric" maxlength="6" required>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="trNewPassword">New Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="trNewPassword" name="newPassword" placeholder="Enter a new password" required minlength="6">
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="trConfirmPassword">Confirm New Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="trConfirmPassword" name="confirmPassword" placeholder="Re-enter the new password" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn-sign-in" id="trSubmitBtn">
                        <i class="bi bi-check-circle-fill"></i> Reset Password
                    </button>
                </form>

                <p class="login-footer-text mt-3">
                    <a href="#" id="showEmailModeLink"><i class="bi bi-arrow-left"></i> Use the email reset link instead</a>
                </p>
            </div>

            <hr class="login-card-divider">

            <p class="login-footer-text">
                <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i> Back to Sign In</a>
            </p>
        </div>

    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Firebase — this page itself only needs Authentication, but
     assets/js/firebase-config.js unconditionally calls
     firebase.firestore() too (it's shared with login.php, which does
     need it for the post-login role lookup), so firestore-compat is
     still loaded here to avoid a console error on that line. -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= asset_url('/assets/js/firebase-config.js') ?>"></script>

<!-- Forgot Password page JS -->
<script src="<?= asset_url('/assets/js/forgot-password.js') ?>"></script>
</body>
</html>
