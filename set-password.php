<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Set Your Password (set-password.php)
 * ---------------------------------------------------------------------
 * Reached only via api/session-login.php's redirect-worthy response
 * {requiresPasswordSetup: true} — api/register.php now sets a real
 * password at registration time for every account, including "Sign up
 * with Google" ones, so this page should rarely come up for an account
 * created from here on. It stays as a fallback for an older "Sign up
 * with Google" account that was approved before that changed and still
 * has no password in Firebase Auth at all, forcing a choice on its
 * next login before any dashboard is reachable. Gated by
 * $_SESSION['pendingPasswordSetupUid'] (set by session-login.php, same
 * pattern as the 2FA pending-uid stash) — not by anything in the
 * request, so this can only ever act on the account that actually just
 * passed Firebase Authentication a moment ago, never an arbitrary uid.
 *
 * Why this matters: without a password, "Forgot password?" silently
 * does nothing for this account (Firebase has no credential to reset),
 * and the account has no sign-in fallback if Google sign-in is ever
 * unavailable to them. This closes that gap for any account that still
 * has it.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/config/constants.php'; // BASE_URL

// No pending password-setup uid in this session — either they reached
// this page directly, or setup already completed. Nothing to do here.
if (empty($_SESSION['pendingPasswordSetupUid'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Your Password — North Super Health Center</title>

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
         RIGHT PANEL — set-password form
         ========================================================= -->
    <div class="login-right">

        <div class="login-card">
            <h2>Set your password</h2>
            <p class="login-card-subtitle">
                You signed up with Google, so your account doesn't have a password yet.
                Set one now so you can also sign in with your email, and so
                "Forgot password?" works if you ever need it.
            </p>

            <div id="spError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

            <form autocomplete="off" id="setPasswordForm">

                <div class="login-field">
                    <label for="spPassword">New password</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-lock-fill field-icon"></i>
                        <input type="password" id="spPassword" name="password" placeholder="At least 6 characters" required minlength="6">
                    </div>
                </div>

                <div class="login-field">
                    <label for="spConfirmPassword">Confirm password</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-lock-fill field-icon"></i>
                        <input type="password" id="spConfirmPassword" name="confirmPassword" placeholder="Re-enter your password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn-sign-in" id="spSubmitBtn">
                    <i class="bi bi-check-circle-fill"></i> Set Password &amp; Continue
                </button>
            </form>
        </div>

    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Set Password page JS -->
<script src="<?= asset_url('/assets/js/set-password.js') ?>"></script>
</body>
</html>
