<?php
/**
 * =====================================================================
 * Public Self-Registration (api/register.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/register.js when someone submits register.php's
 * "Create an account" form — either the manual email/password path
 * (creates a real Firebase Auth account server-side via the Admin SDK,
 * same pattern as api/create-subadmin.php — NOT the client-side SDK,
 * so this never signs the new account in anywhere), or the "Sign up
 * with Google" path ($body['googleIdToken'] set — the client already
 * created/authenticated the Firebase Auth account via a real popup;
 * this endpoint verifies that token server-side instead of calling
 * createUser(), and never trusts a client-supplied uid/email for that
 * path). Either way, a password is required and set here too —
 * changeUserPassword() on the Google path, adding a password
 * credential to the SAME Firebase Auth account the popup just created,
 * rather than leaving that account passwordless until a sub-admin
 * approves it later (api/session-login.php's password-setup gate only
 * exists now as a fallback for accounts created before this changed).
 * Either way, the result is the same matching Firestore users/{uid}
 * profile document.
 *
 * SECURITY: the requester picks a "Role" (job title) themselves, so
 * that choice alone must never grant real access. Two layers:
 *   1. The role dropdown (register.php) — and the $jobTitleToRole map
 *      below, which is the actual enforcement point since this
 *      endpoint doesn't trust the client for anything else either —
 *      is deliberately limited to job titles that only ever resolve
 *      to "healthcare_staff", "pharmacist", "medical_technician", or
 *      "doctor". Self-registration can never produce an admin or
 *      subadmin account, full stop.
 *   2. Every account created here gets status="pending" unconditionally
 *      regardless of role; api/session-login.php refuses to start a
 *      session for a non-active account, so a pending account has no
 *      functional access until a sub-admin approves it via
 *      api/approve-account.php (see subadmin/user_management.php's
 *      "Pending Approvals" table — deciding on these requests is a
 *      sub-admin capability, not admin's). The requested job title is
 *      stored alongside the resolved role (as "jobTitle") so an
 *      approving sub-admin can see exactly what was asked for.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/mailer.php'; // hc_send_email(), hc_account_email_shell()
require_once __DIR__ . '/../includes/firestore/accounts.php'; // hc_email_already_registered()

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$firstName = trim((string) ($body['firstName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');
$jobTitle = trim((string) ($body['role'] ?? ''));
$termsAccepted = (bool) ($body['termsAccepted'] ?? false);
// Set only by "Sign up with Google" (assets/js/register.js) — an
// alternate identity-verification method, not a shortcut around
// anything below: same required fields (including password — see
// below), same $jobTitleToRole enforcement, same unconditional
// "pending" status. Just skips createUser(), since Firebase Auth
// already created this account the moment the Google popup succeeded
// — this endpoint verifies that token server-side, adds the password
// to that same account via changeUserPassword(), and writes the
// matching Firestore profile.
$googleIdToken = trim((string) ($body['googleIdToken'] ?? ''));
$isGoogleSignup = $googleIdToken !== '';

// Job title (as picked in the UI) -> real underlying app role. Kept
// deliberately narrow, for now, to titles that only ever resolve to
// "healthcare_staff", "pharmacist", "medical_technician", or "doctor"
// — see SECURITY note above. This map (not the <select> in
// register.php) is the actual enforcement point, since a request
// could always be crafted by hand. "Doctor" is its own role, distinct
// from "Healthcare Staff" — see includes/doctor/doctor_dashboard_firestore.php's
// header comment for why the two are split.
$jobTitleToRole = [
    'Healthcare Staff' => 'healthcare_staff',
    'Pharmacist' => 'pharmacist',
    'Medical Technologist' => 'medical_technician',
    'Radiologic Technologist' => 'radio_technologist',
    'Doctor' => 'doctor',
];

// Phone is the one field Google signups skip — Google never hands one
// over, so it's optional there (added later from Profile Settings,
// nudged by includes/shared/phone_reminder.php's reminder notification/
// toast), while still required on the manual path.
if ($firstName === '' || $lastName === '' || $email === '' || $jobTitle === '' || $password === '' || (!$isGoogleSignup && $phone === '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}
// Password is required either way now — see this file's header
// comment for why the Google path isn't exempt from this any more.
if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}
if ($password !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit;
}
if (!$isGoogleSignup && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
if (!isset($jobTitleToRole[$jobTitle])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a valid role.']);
    exit;
}
// The checkbox's own required attribute + register.js's client-side
// check already stop this in the normal UI flow — this is the real
// enforcement point, since a request could always be sent by hand.
if (!$termsAccepted) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please agree to the Terms of Service and Privacy Policy to continue.']);
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);
$role = $jobTitleToRole[$jobTitle];

// hc_email_already_registered() (includes/firestore/accounts.php) is
// the real enforcement point for "one email = at most one account" —
// see that function's own comment for why it's checked by email, not
// Firebase Auth uid, and why it's shared across every real
// account-creation endpoint rather than kept as a local copy here.
if ($isGoogleSignup) {
    try {
        $verifiedToken = $firebaseAuth->verifyIdToken($googleIdToken);
    } catch (\Throwable $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Your Google sign-in has expired. Please try again.']);
        exit;
    }
    $uid = $verifiedToken->claims()->get('sub');
    // The verified token's own email, never the client-supplied $email
    // above — this is the one field that must not be trusted from the
    // request body on this path.
    $email = $verifiedToken->claims()->get('email');

    if (hc_email_already_registered($email)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'An account already exists for this email. Please sign in instead.']);
        exit;
    }

    // Adds a password credential to the SAME Firebase Auth account the
    // Google popup just created — it does not replace or remove the
    // Google sign-in method, so this account can sign in either way
    // afterward. This is what makes the password exist right now, at
    // registration time, instead of this account sitting passwordless
    // until a sub-admin approves it and api/session-login.php's
    // password-setup gate catches it on first login.
    try {
        $firebaseAuth->changeUserPassword($uid, $password);
    } catch (\Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Could not set your password: ' . $e->getMessage()]);
        exit;
    }
} else {
    if (hc_email_already_registered($email)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.']);
        exit;
    }
    try {
        $userRecord = $firebaseAuth->createUser([
            'email' => $email,
            'password' => $password,
            'displayName' => $fullName,
        ]);
    } catch (\Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Could not create account: ' . $e->getMessage()]);
        exit;
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
    // Self-registration has no facility picker of its own — every
    // request here is auto-assigned to this clinic's own facility
    // rather than left unset, so a sub-admin approving it (and every
    // per-facility page the approved account lands on afterward) has a
    // real facility to work with immediately. Matches the exact 'name'
    // field facilities/north-super-health-center carries (see
    // includes/admin/facility_management_firestore.php) — must stay in
    // sync with that if this clinic's facility is ever renamed.
    'facility' => 'North Super Health Center',
    'status' => 'pending',
    'createdAt' => date('c'),
    // A real record of when this account agreed to the Terms of
    // Service/Privacy Policy (register.php's modals) — the request
    // itself is already refused above if termsAccepted wasn't true, so
    // reaching this point means "now" always is that moment.
    'termsAcceptedAt' => date('c'),
    // Display/audit only — api/session-login.php's actual password-setup
    // GATE checks the real Firebase Auth provider list at login time
    // (see that file's comment), not this field. Both paths above now
    // set a real password at registration time, so that gate should
    // rarely fire for accounts created from here on — it stays in
    // place as a fallback for accounts created before this changed.
    'authProvider' => $isGoogleSignup ? 'google' : 'password',
]);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Account was created but saving the profile failed. Please try again or contact support.']);
    exit;
}

write_audit_log('created', 'User Management', 'New account request from ' . $fullName . ' (' . $email . ') requesting "' . $jobTitle . '" — pending approval.');

hc_send_email($email, $fullName, 'Your HEALTrack account request has been received', hc_account_email_shell(
    'Request received, ' . $firstName . '!',
    '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Thanks for signing up for HEALTrack as a <strong>' . htmlspecialchars($jobTitle) . '</strong>.</p>'
    . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Your request is now pending review by a sub-administrator. You\'ll get another email as soon as a decision is made — there\'s nothing else you need to do right now.</p>'
));

echo json_encode(['success' => true]);
