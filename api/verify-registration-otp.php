<?php
/**
 * =====================================================================
 * Verify Registration Email OTP (api/verify-registration-otp.php)
 * ---------------------------------------------------------------------
 * Step 3 of the manual self-registration flow — called by the OTP
 * modal (register.php / assets/js/register.js) after
 * api/send-registration-otp.php emailed a 6-digit code. Reads ONLY
 * $_SESSION['pendingRegistration'] — never a client-supplied email or
 * identifier — same principle api/verify-2fa.php already uses for
 * $_SESSION['pending2faUid'], so a code can only ever be checked
 * against the registration that actually just requested it from this
 * same browser session.
 *
 * On a correct code, this is the ONLY moment a self-typed-email
 * registration actually creates its Firebase Auth user + Firestore
 * users/{uid} profile (hc_finish_registration(),
 * includes/shared/registration.php) — nothing exists anywhere before
 * this, so there is nothing for the sub-admin's Pending Approvals table
 * to show until the email is proven owned.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/registration.php'; // hc_finish_registration(), HC_JOB_TITLE_TO_ROLE

header('Content-Type: application/json');

const HC_REG_OTP_MAX_ATTEMPTS = 5;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$pending = $_SESSION['pendingRegistration'] ?? null;
if ($pending === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Your registration session has expired. Please fill out the form again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$otp = trim((string) ($body['otp'] ?? ''));

if ($otp === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit code.']);
    exit;
}

if (time() > (int) $pending['otpExpiresAt']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This code has expired. Please request a new one.', 'expired' => true]);
    exit;
}

if ((int) $pending['attempts'] >= HC_REG_OTP_MAX_ATTEMPTS) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Too many incorrect attempts. Please request a new code.', 'locked' => true]);
    exit;
}

if (!password_verify($otp, $pending['otpHash'])) {
    $pending['attempts'] = (int) $pending['attempts'] + 1;
    $_SESSION['pendingRegistration'] = $pending;
    $remaining = HC_REG_OTP_MAX_ATTEMPTS - $pending['attempts'];
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $remaining > 0
            ? 'That code is incorrect. ' . $remaining . ' attempt(s) remaining.'
            : 'That code is incorrect. Please request a new code.',
        'locked' => $remaining <= 0,
    ]);
    exit;
}

// Correct code — this OTP is now consumed regardless of what happens
// below, so it can never be replayed even if account creation fails.
unset($_SESSION['pendingRegistration']);

if (hc_email_already_registered($pending['email'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.']);
    exit;
}

$result = hc_finish_registration([
    'firstName' => $pending['firstName'],
    'lastName' => $pending['lastName'],
    'email' => $pending['email'],
    'phone' => $pending['phone'],
    'jobTitle' => $pending['jobTitle'],
    'role' => HC_JOB_TITLE_TO_ROLE[$pending['jobTitle']],
    'password' => $pending['password'],
    'uid' => null,
    'isGoogleSignup' => false,
]);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode(['success' => true]);
