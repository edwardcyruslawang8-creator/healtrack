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
 *      Healthcare Staff / Pharmacist / Medical Technologist / Radio
 *      Technologist / Doctor — every one of those maps to
 *      "healthcare_staff", "pharmacist", "medical_technician",
 *      "radio_technologist", or "doctor" (api/register.php's
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
                        <!-- "(optional)" shown only after a successful "Sign up with
                             Google" — Google doesn't hand over a phone number, so this
                             is no longer required to finish that path; it can be added
                             later from Profile Settings instead (see
                             includes/shared/phone_reminder.php for the in-app/toast
                             reminder that nudges that). Still required for the manual
                             password path. assets/js/register.js toggles this. -->
                        <label for="regPhone">Phone Number<span id="regPhoneOptionalNote" class="reg-optional-note d-none"> (optional — you can add this later in Profile Settings)</span></label>
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
                            <option value="Radio Technologist">Radio Technologist</option>
                            <option value="Doctor">Doctor</option>
                        </select>
                    </div>
                </div>

                <div class="reg-terms">
                    <input type="checkbox" id="regTerms" required>
                    <label for="regTerms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>.</label>
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

<!-- =========================================================
     TERMS OF SERVICE / PRIVACY POLICY — opened either by their own
     links in the "I agree to..." row, OR forced open automatically
     (assets/js/register.js) the moment someone clicks "Create
     Account" or "Sign up with Google" before having checked
     #regTerms — so the actual text is shown, not just an easy
     checkbox to tick unread. Each modal's own "I Agree" button
     checks #regTerms and closes it; clicking Create Account/Google
     again then proceeds normally. Agreement itself is still only
     ever recorded via that one checkbox + its required
     "termsAccepted" flag (assets/js/register.js posts it,
     api/register.php refuses any request without it, and the
     approved account's own users/{uid} doc keeps "termsAcceptedAt"
     as a real record of when).
     ========================================================= -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body reg-legal-body">
                <p class="reg-legal-updated">Last updated: <?= date('F Y') ?></p>

                <h6>1. Who this account is for</h6>
                <p>HEALTrack is a clinical information system for North Super Health Center's own staff — Healthcare Staff, Pharmacists, Medical Technologists, and Doctors. By registering, you confirm that the name, email, phone number, and role you provide are accurate and your own, and that you are a real member of this facility's staff requesting access in that capacity.</p>

                <h6>2. Account review and approval</h6>
                <p>Creating an account here only submits a request. Every new account starts in a pending state and has no access to any patient or facility data until a sub-administrator reviews and approves it. Submitting a request does not guarantee approval, and a request may be rejected or the role you asked for may be questioned before approval.</p>

                <h6>3. Acceptable use</h6>
                <p>Once approved, your access is for legitimate clinical and administrative work at this facility only. You agree not to: share your login credentials with anyone else; access patient records you have no clinical or administrative reason to view; attempt to bypass the role-based access controls built into the system; or use the system in any way that could compromise patient safety, data integrity, or the privacy of patients or other staff.</p>

                <h6>4. Confidentiality of health information</h6>
                <p>Any patient health information you can see through your approved role is confidential. You agree to access, use, and disclose it only as required for your clinical or administrative duties, and never for personal curiosity, convenience, or any purpose outside your role — consistent with this facility's own confidentiality policies and applicable health-data regulations.</p>

                <h6>5. Account security</h6>
                <p>You are responsible for keeping your password confidential and for all activity under your account. Tell a sub-administrator immediately if you believe your account has been accessed without your authorization. Sessions are automatically signed out after a period of inactivity for everyone's protection.</p>

                <h6>6. Service availability</h6>
                <p>HEALTrack is provided on an as-available basis for this facility's internal use. Features, pages, and this agreement may change as the system evolves; continued use after a change means you accept the updated terms.</p>

                <h6>7. Suspension and termination</h6>
                <p>Access may be suspended or removed at any time for a violation of these terms, at the end of your employment or engagement with this facility, or at a sub-administrator's or administrator's discretion.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <!-- Checks the single #regTerms checkbox and closes the
                     modal (assets/js/register.js) — the actual
                     affirmative "I read this" action, since this modal
                     is now forced open before Create Account/Google
                     sign-up can proceed without it. -->
                <button type="button" class="reg-legal-agree-btn" id="termsAgreeBtn">I Agree</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body reg-legal-body">
                <p class="reg-legal-updated">Last updated: <?= date('F Y') ?></p>

                <h6>1. What we collect when you register</h6>
                <p>Your first and last name, email address, phone number, and the role/job title you select. If you sign up with Google, we also receive the name and email already associated with that Google account instead of asking you to type them. None of this is shared with or sold to any outside party — it exists only to identify you within this facility's own HEALTrack system.</p>

                <h6>2. What we collect once you're approved</h6>
                <p>Your day-to-day use of the system — the pages you visit, actions you take (such as updating a record), and sign-in activity — is recorded in an internal audit log, the same as every other staff account. This exists for accountability and security, not general monitoring, and is visible only to administrators and sub-administrators.</p>

                <h6>3. How your account information is used</h6>
                <p>To create and manage your staff account, route your registration request to a sub-administrator for review, let you sign in once approved, enforce what your specific role is allowed to see and do, and contact you (by email) about your account — for example, when your request is approved or rejected.</p>

                <h6>4. Who can see your account information</h6>
                <p>Administrators and sub-administrators can see your registration details while reviewing your request and afterward, as part of staff account management. Your name and role are visible to other staff in ordinary places the system already shows who did what (for example, audit logs and assignment lists) — your password is never visible to anyone, including administrators; it is stored only as a secure credential inside Firebase Authentication.</p>

                <h6>5. Patient health information</h6>
                <p>This policy covers your own account data. Any patient health information you access once approved is governed separately by this facility's confidentiality obligations and the Terms of Service's Section 4 — it is never used for anything beyond the clinical/administrative purpose it was accessed for.</p>

                <h6>6. Data retention</h6>
                <p>Your account information is kept for as long as your account exists, and for a reasonable period afterward for legitimate recordkeeping (for example, audit history tied to actions you took while active). A rejected registration request is retained only long enough to prevent a duplicate request under the same email.</p>

                <h6>7. Your choices</h6>
                <p>You can ask an administrator or sub-administrator to review, correct, or deactivate your account at any time. If you signed up with a password, you can change it from your own Profile / Settings once your account is approved.</p>

                <h6>8. Contact</h6>
                <p>Questions about this policy or your data can be directed to a sub-administrator or administrator at North Super Health Center.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="reg-legal-agree-btn" id="privacyAgreeBtn">I Agree</button>
            </div>
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
