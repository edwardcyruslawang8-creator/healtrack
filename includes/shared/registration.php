<?php
/**
 * =====================================================================
 * Shared — Finish Self-Registration (includes/shared/registration.php)
 * ---------------------------------------------------------------------
 * The one place that actually creates a self-registered account record
 * (Firebase Auth user/password + the Firestore users/{uid} profile,
 * status="pending", plus the audit log line and "request received"
 * email) — extracted out of api/register.php so there is exactly one
 * implementation, called from two places:
 *   - api/register.php's "Sign up with Google" branch (identity already
 *     proven by a real Firebase Auth popup + verified ID token, so no
 *     OTP needed — see that file's header comment).
 *   - api/verify-registration-otp.php, the moment a self-typed
 *     email+password registration's OTP is confirmed correct (see that
 *     file's header comment for the full OTP flow).
 * Neither caller writes any of this itself any more.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../firestore/accounts.php'; // hc_email_already_registered()

if (!defined('HC_JOB_TITLE_TO_ROLE')) {
    // Job title (as picked in the UI) -> real underlying app role. Kept
    // deliberately narrow, for now, to titles that only ever resolve to
    // "healthcare_staff", "pharmacist", "medical_technician",
    // "radio_technologist", or "doctor" — self-registration can never
    // itself produce an admin or subadmin account, no matter what's
    // submitted. Shared by every endpoint that accepts a self-registration
    // request (api/register.php, api/send-registration-otp.php) instead
    // of each keeping its own copy that could drift out of sync.
    define('HC_JOB_TITLE_TO_ROLE', [
        'Healthcare Staff' => 'healthcare_staff',
        'Pharmacist' => 'pharmacist',
        'Medical Technologist' => 'medical_technician',
        'Radiologic Technologist' => 'radio_technologist',
        'Doctor' => 'doctor',
    ]);
}

if (!function_exists('hc_validate_registration_fields')) {
    /**
     * The same field checks api/register.php has always done (required
     * fields, password length/match, email format, role whitelist,
     * terms accepted) — shared so api/send-registration-otp.php (the
     * manual-registration path) and api/register.php's Google path
     * enforce identically, rather than two copies that could drift.
     * $requirePhone/$requireEmailFormat are false for the Google path
     * (phone is optional there; email comes from an already-verified
     * token, not user input).
     *
     * @return string|null The first validation error, or null if valid.
     */
    function hc_validate_registration_fields(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        string $jobTitle,
        string $password,
        string $confirmPassword,
        bool $termsAccepted,
        bool $requirePhone = true,
        bool $requireEmailFormat = true
    ): ?string {
        if ($firstName === '' || $lastName === '' || $email === '' || $jobTitle === '' || $password === '' || ($requirePhone && $phone === '')) {
            return 'Please fill out every field.';
        }
        if (strlen($password) < 6) {
            return 'Password must be at least 6 characters.';
        }
        if ($password !== $confirmPassword) {
            return 'Passwords do not match.';
        }
        if ($requireEmailFormat && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }
        if (!isset(HC_JOB_TITLE_TO_ROLE[$jobTitle])) {
            return 'Please select a valid role.';
        }
        if (!$termsAccepted) {
            return 'Please agree to the Terms of Service and Privacy Policy to continue.';
        }

        return null;
    }
}

if (!function_exists('hc_finish_registration')) {
    /**
     * @param array{
     *     firstName: string, lastName: string, email: string, phone: string,
     *     jobTitle: string, role: string, password: string,
     *     uid: ?string, isGoogleSignup: bool
     * } $data 'uid' is required (and already-authenticated) when
     *     isGoogleSignup is true; ignored otherwise — a brand-new
     *     Firebase Auth user is created here instead.
     * @return array{success: bool, error: ?string, uid: ?string}
     */
    function hc_finish_registration(array $data): array
    {
        global $firestore, $firebaseAuth;

        $firstName = $data['firstName'];
        $lastName = $data['lastName'];
        $email = $data['email'];
        $phone = $data['phone'];
        $jobTitle = $data['jobTitle'];
        $role = $data['role'];
        $password = $data['password'];
        $isGoogleSignup = $data['isGoogleSignup'];
        $fullName = trim($firstName . ' ' . $lastName);

        // Re-checked here (not just by the caller) since some callers
        // validate this, wait on user input (typing an OTP), and only
        // reach this function afterward — someone else could have
        // registered the same email in the meantime.
        if (hc_email_already_registered($email)) {
            return ['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.', 'uid' => null];
        }

        if ($isGoogleSignup) {
            $uid = $data['uid'];
            try {
                // Adds a password credential to the SAME Firebase Auth
                // account the Google popup already created — doesn't
                // replace or remove the Google sign-in method.
                $firebaseAuth->changeUserPassword($uid, $password);
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Could not set your password: ' . $e->getMessage(), 'uid' => null];
            }
        } else {
            try {
                $userRecord = $firebaseAuth->createUser([
                    'email' => $email,
                    'password' => $password,
                    'displayName' => $fullName,
                ]);
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Could not create account: ' . $e->getMessage(), 'uid' => null];
            }
            $uid = $userRecord->uid;
        }

        $saved = $firestore->setDocument('users', $uid, [
            'name' => $fullName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'jobTitle' => $jobTitle,
            // Self-registration has no facility picker of its own —
            // every request here is auto-assigned to this clinic's own
            // facility. Must stay in sync with
            // facilities/north-super-health-center's own 'name' field
            // if this clinic is ever renamed.
            'facility' => 'North Super Health Center',
            'status' => 'pending',
            'createdAt' => date('c'),
            'termsAcceptedAt' => date('c'),
            // Real record of when OWNERSHIP of this email was proven —
            // either this moment's correct email-OTP entry, or (Google
            // path) the moment the verified Google ID token's own
            // email_verified claim was confirmed true, right before this
            // function was called. Distinct from Firebase Auth's own
            // emailVerified flag (which this never touches), and what
            // subadmin/user_management.php's Pending Approvals table
            // badges as "Verified".
            'emailVerifiedAt' => date('c'),
            'authProvider' => $isGoogleSignup ? 'google' : 'password',
        ]);

        if (!$saved) {
            return ['success' => false, 'error' => 'Account was created but saving the profile failed. Please try again or contact support.', 'uid' => null];
        }

        write_audit_log('created', 'User Management', 'New account request from ' . $fullName . ' (' . $email . ') requesting "' . $jobTitle . '" — pending approval.');

        hc_send_email($email, $fullName, 'Your HEALTrack account request has been received', hc_account_email_shell(
            'Request received, ' . $firstName . '!',
            '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Thanks for signing up for HEALTrack as a <strong>' . htmlspecialchars($jobTitle) . '</strong>.</p>'
            . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Your request is now pending review by a sub-administrator. You\'ll get another email as soon as a decision is made — there\'s nothing else you need to do right now.</p>'
        ));

        return ['success' => true, 'error' => null, 'uid' => $uid];
    }
}
