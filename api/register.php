<?php
/**
 * =====================================================================
 * Public Self-Registration — Google Sign-Up Only (api/register.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/register.js ONLY for the "Sign up with Google"
 * path — the client already created/authenticated the Firebase Auth
 * account via a real popup ($body['googleIdToken'] set); this endpoint
 * verifies that token server-side instead of calling createUser(), and
 * never trusts a client-supplied uid/email for that path.
 *
 * The manual email+password path used to be handled here too, but now
 * goes through api/send-registration-otp.php ->
 * api/verify-registration-otp.php instead, so a self-typed email is
 * proven to actually belong to the requester (a 6-digit OTP) before any
 * account record is created at all — see that pair's header comments.
 * Google's own popup + this endpoint's verifyIdToken() call (plus the
 * email_verified claim check below) is already stronger proof of email
 * ownership than a new custom OTP would add, so that path is
 * deliberately NOT routed through the OTP flow.
 *
 * Any request here WITHOUT a googleIdToken is refused outright — this
 * is the actual enforcement point that stops the OTP step from being
 * bypassable by a hand-crafted request straight to this endpoint.
 *
 * SECURITY: the requester picks a "Role" (job title) themselves, so
 * that choice alone must never grant real access. HC_JOB_TITLE_TO_ROLE
 * (includes/shared/registration.php) is deliberately limited to job
 * titles that only ever resolve to "healthcare_staff", "pharmacist",
 * "medical_technician", "radio_technologist", or "doctor" — self-
 * registration can never produce an admin or subadmin account. Every
 * account created here gets status="pending" unconditionally (see
 * hc_finish_registration()); api/session-login.php refuses to start a
 * session for a non-active account.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/registration.php'; // hc_validate_registration_fields(), hc_finish_registration(), HC_JOB_TITLE_TO_ROLE

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
$googleIdToken = trim((string) ($body['googleIdToken'] ?? ''));

if ($googleIdToken === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please verify your email to continue registering.']);
    exit;
}

// Phone is optional on this path (Google never hands one over); email
// format isn't checked against user input since it's overwritten below
// with the verified token's own email regardless of what was submitted.
$validationError = hc_validate_registration_fields($firstName, $lastName, $email, $phone, $jobTitle, $password, $confirmPassword, $termsAccepted, false, false);
if ($validationError !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $validationError]);
    exit;
}

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

// Google/Firebase should always hand back a verified email for this
// sign-in method — this is a defensive backstop, not the primary proof,
// in case that claim is ever explicitly false for some linked-identity
// edge case this app doesn't otherwise expect.
if ($verifiedToken->claims()->get('email_verified') === false) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Your Google account\'s email isn\'t verified. Please verify it with Google first, or register with email and password instead.']);
    exit;
}

if (hc_email_already_registered($email)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'An account already exists for this email. Please sign in instead.']);
    exit;
}

$result = hc_finish_registration([
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'jobTitle' => $jobTitle,
    'role' => HC_JOB_TITLE_TO_ROLE[$jobTitle],
    'password' => $password,
    'uid' => $uid,
    'isGoogleSignup' => true,
]);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true]);
