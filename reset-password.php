<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Reset Password (reset-password.php)
 * ---------------------------------------------------------------------
 * Where forgot-password.php's real reset-link emails now land (see
 * assets/js/forgot-password.js's sendPasswordResetEmail() call —
 * actionCodeSettings.handleCodeInApp: true routes the email link HERE
 * instead of to Firebase's own default hosted reset page, specifically
 * so this app gets a real moment to react once a password is actually
 * reset). assets/js/reset-password.js does the real work client-side:
 * verifyPasswordResetCode() to validate the real ?oobCode= and learn
 * which email it's for, then confirmPasswordReset() to actually set the
 * new password (both straight against Firebase Auth — no api/*.php
 * endpoint is involved in the password change itself). Only once THAT
 * already succeeded does it call api/report-password-reset-completed.php,
 * which revokes every "remember me" token for this account and writes a
 * real audit log entry — see that file's own header comment for why.
 *
 * Reuses login.php's exact left-panel branding and assets/css/login.css,
 * same as forgot-password.php — one shared pre-login visual shell.
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
<title>Reset Password — North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Base stylesheet (shared CSS variables/fonts only — this page doesn't use the dashboard sidebar) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<!-- Reused verbatim from login.php/forgot-password.php — same card/field/left-panel styles -->
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
         RIGHT PANEL — reset-password form
         ========================================================= -->
    <div class="login-right">

        <div class="login-card">

            <!-- Shown once the real ?oobCode= is confirmed valid (assets/js/reset-password.js) -->
            <div id="rpFormState">
                <h2>Choose a new password</h2>
                <p class="login-card-subtitle" id="rpSubtitle">Verifying your reset link&hellip;</p>

                <div id="rpError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

                <form autocomplete="off" id="resetPasswordForm" class="d-none">
                    <div class="login-field">
                        <label for="rpNewPassword">New Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="rpNewPassword" name="newPassword" placeholder="Enter a new password" required minlength="6">
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="rpConfirmPassword">Confirm New Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="rpConfirmPassword" name="confirmPassword" placeholder="Re-enter the new password" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn-sign-in" id="rpSubmitBtn">
                        <i class="bi bi-check-circle-fill"></i> Reset Password
                    </button>
                </form>
            </div>

            <!-- Shown once the password is actually changed -->
            <div id="rpSuccessState" class="d-none">
                <h2>Password updated</h2>
                <p class="login-card-subtitle">Your password has been changed. You can now sign in with your new password.</p>
                <a href="<?= BASE_URL ?>/login.php" class="btn-sign-in d-inline-flex justify-content-center">
                    <i class="bi bi-box-arrow-in-right"></i> Back to Sign In
                </a>
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
     firebase.firestore() too (shared with login.php/forgot-password.php,
     which do need it), so firestore-compat is still loaded here to
     avoid a console error on that line. -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= asset_url('/assets/js/firebase-config.js') ?>"></script>

<!-- Reset Password page JS -->
<script src="<?= asset_url('/assets/js/reset-password.js') ?>"></script>
</body>
</html>
