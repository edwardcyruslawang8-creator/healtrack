<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Create Account / Registration Page (register.php)
 * ---------------------------------------------------------------------
 * Public self-registration — previously login.php's header comment
 * explicitly documented "there's no public sign-up here on purpose."
 * This page changes that, but with two safety rails:
 *   1. The Role dropdown is deliberately limited, for now, to
 *      Healthcare Staff / Pharmacist / Medical Technologist / Doctor —
 *      every one of those maps to "healthcare_staff", "pharmacist",
 *      "medical_technician", or "doctor" (api/register.php's
 *      $jobTitleToRole), so self-registration can never itself
 *      produce an admin or subadmin account, no matter what's selected.
 *   2. Regardless of role, every account created here starts with
 *      status="pending" and has NO functional access until a sub-admin
 *      approves it in User Management (subadmin/user_management.php's
 *      "Pending Approvals" table, api/approve-account.php) — deciding
 *      on these requests is a sub-admin capability, not admin's.
 *      api/session-login.php refuses to start a session for a
 *      non-active account, so a pending account can't just log in and
 *      use the system while waiting — that block is enforced
 *      server-side, not just hidden in the UI.
 *
 * "Medical Technologist" maps to its own "medical_technician" role
 * (medical_technician/dashboard.php), and "Doctor" maps to its own
 * "doctor" role (doctor/dashboard.php) — api/register.php keeps the
 * requested title on the account doc as "jobTitle" too, so an
 * approving sub-admin can still see exactly what was asked for.
 *
 * Account creation itself happens server-side (api/register.php, via
 * the Firebase Admin SDK — same pattern as api/create-subadmin.php),
 * not client-side Firebase Auth, so a brand-new pending account is
 * never signed in anywhere while awaiting approval.
 *
 * "Sign up with Google" (assets/js/register.js) is an alternate
 * CREDENTIAL method, not a shortcut around any of the above — it only
 * verifies who someone is (via a real client-side Firebase Auth
 * popup), then submits the SAME api/register.php request the manual
 * form does (Phone + Role still required either way; Password fields
 * just aren't needed/shown), landing in the same "pending" state.
 * api/register.php verifies the resulting idToken itself server-side
 * (never trusts a client-supplied uid/email for that path) and skips
 * only the createUser() step, since Firebase Auth already created that
 * account the moment the Google popup succeeded.
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
<title>Create an Account — North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Base stylesheet (shared CSS variables/fonts) + the same login shell login.php uses -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/login.css') ?>">
<!-- Registration-specific additions (two-column field grid, role select, terms row) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/register.css') ?>">

<script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>
</head>
<body>

<div class="login-page">

    <!-- =========================================================
         LEFT PANEL — brand / illustration (same as login.php)
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
            Empowering barangays with data-driven insights and efficient healthcare management.
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
         RIGHT PANEL — registration form
         ========================================================= -->
    <div class="login-right reg-right">

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

        <!-- Registration card -->
        <div class="login-card reg-card">
            <h2>Create an account</h2>
            <p class="login-card-subtitle">Register to get started with the North Super Health Center system.</p>

            <div id="regSuccess" class="alert alert-success py-2 px-3 d-none" style="font-size:13px;"></div>
            <div id="regError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

            <form id="registerForm" autocomplete="off" novalidate>

                <div class="reg-grid">
                    <div class="login-field">
                        <label for="regFirstName">First Name</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-person-fill field-icon"></i>
                            <input type="text" id="regFirstName" placeholder="Enter your first name" required>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="regLastName">Last Name</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-person-fill field-icon"></i>
                            <input type="text" id="regLastName" placeholder="Enter your last name" required>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="regEmail">Email Address</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-envelope-fill field-icon"></i>
                            <input type="email" id="regEmail" placeholder="Enter your email address" required>
                        </div>
                    </div>
                    <div class="login-field">
                        <label for="regPhone">Phone Number</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-telephone-fill field-icon"></i>
                            <input type="text" id="regPhone" placeholder="Enter your phone number" required>
                        </div>
                    </div>

                    <!-- Hidden entirely after a successful "Sign up with
                         Google" above — Google already authenticated
                         you, so no password is needed (assets/js/register.js). -->
                    <div class="login-field" id="regPasswordField">
                        <label for="regPassword">Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="regPassword" class="has-toggle" placeholder="Create a password" minlength="6">
                            <button type="button" class="login-toggle-btn reg-toggle" data-target="regPassword" aria-label="Show or hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="login-field" id="regConfirmPasswordField">
                        <label for="regConfirmPassword">Confirm Password</label>
                        <div class="login-input-wrap">
                            <i class="bi bi-lock-fill field-icon"></i>
                            <input type="password" id="regConfirmPassword" class="has-toggle" placeholder="Confirm your password" minlength="6">
                            <button type="button" class="login-toggle-btn reg-toggle" data-target="regConfirmPassword" aria-label="Show or hide password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="login-field">
                    <label for="regRole">Role</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-person-vcard-fill field-icon"></i>
                        <select id="regRole" class="reg-select" required>
                            <option value="" selected disabled>Select your role</option>
                            <option value="Healthcare Staff">Healthcare Staff</option>
                            <option value="Pharmacist">Pharmacist</option>
                            <option value="Medical Technologist">Medical Technologist</option>
                            <option value="Doctor">Doctor</option>
                        </select>
                    </div>
                </div>

                <div class="reg-terms">
                    <input type="checkbox" id="regTerms" required>
                    <label for="regTerms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</label>
                </div>

                <!-- Every request here is reviewed before it grants any access —
                     see this page's header comment for why. -->
                <p class="reg-pending-note">
                    <i class="bi bi-info-circle"></i>
                    Your account will be reviewed by a sub-administrator before you can sign in.
                </p>

                <!-- Verifies who you are with Google, then prefills Name/Email
                     above (Email locked, Password fields hidden — Google
                     already authenticated you) — account creation is still
                     the SAME server-side api/register.php request either way
                     (see assets/js/register.js), still "pending" until a
                     sub-admin approves it. Phone and Role are still required
                     either way — Google doesn't provide either. Positioned
                     directly above the Create Account button rather than
                     at the top of the form. -->
                <button type="button" class="btn-google-signin" id="googleSignUpBtn">
                    <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                        <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                    </svg>
                    Sign up with Google
                </button>
                <!-- Hidden until "Sign up with Google" actually succeeds
                     (assets/js/register.js) — undoes it: clears
                     googleIdToken, unlocks Email, restores the Password
                     fields, and puts the Google button back to its
                     default clickable state, so someone who signed in
                     with the wrong Google account (or just changes their
                     mind) can go back to typing the form manually
                     instead of being stuck with a locked email. -->
                <button type="button" class="reg-google-back-btn d-none" id="googleSignUpBackBtn">
                    <i class="bi bi-arrow-left"></i> Back to filling out the form manually
                </button>
                <div class="login-or-divider">or</div>

                <button type="submit" class="btn-sign-in" id="registerBtn">
                    <i class="bi bi-person-plus-fill"></i> Create Account
                </button>
            </form>

            <hr class="login-card-divider">

            <p class="login-footer-text">
                Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in here.</a>
            </p>
        </div>

    </div>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Firebase (Authentication only, for "Sign up with Google" — same
     project/config login.php uses via assets/js/firebase-config.js) -->
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= asset_url('/assets/js/firebase-config.js') ?>"></script>

<!-- Registration page JS -->
<script src="<?= asset_url('/assets/js/register.js') ?>"></script>
</body>
</html>
