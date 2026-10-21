<?php
/**
 * =====================================================================
 * Reset Password with Authenticator Code (api/reset-password-with-totp.php)
 * ---------------------------------------------------------------------
 * Alternative to forgot-password.php's email-link flow, for an account
 * that already has 2FA enabled: proves identity with a real 6-digit
 * TOTP code (the SAME check api/verify-2fa.php's login step already
 * does — PragmaRX\Google2FA, verified against this account's own real
 * twoFactorSecret) instead of needing a reset email to actually arrive.
 * No session/login step happens here at all — this only changes the
 * password, exactly like confirmPasswordReset() does in the email-link
 * flow; the person still signs in normally afterward with their new
 * password.
 *
 * ANTI-ENUMERATION: unknown email, email with no 2FA enabled, and a
 * genuinely wrong code all return the exact SAME generic error — none
 * of the three can be told apart from the response, same principle
 * forgot-password.js's own email flow already follows for "account
 * doesn't exist" vs "account exists." Only once the code is actually
 * CORRECT does this move on to a real (and therefore safe-to-be-
 * specific-about) failure like a rejected weak password.
 *
 * Real side effects once the code checks out: the password actually
 * changes (Kreait Admin SDK's changeUserPassword(), same call
 * api/complete-password-setup.php already uses), every "remember me"
 * token for this account is revoked (hc_revoke_all_remember_tokens_for_uid()
 * — same reasoning as reset-password.php's email-link flow), and a
 * real audit_logs entry is written directly (no session exists here
 * either — the person isn't signed in).
 * =====================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/../includes/shared/remember_me.php'; // hc_revoke_all_remember_tokens_for_uid()

use PragmaRX\Google2FA\Google2FA;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$GENERIC_ERROR = 'That email and code combination isn\'t valid, or this account isn\'t set up for code-based reset. Please use the email reset link instead, or contact your administrator.';

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim((string) ($body['email'] ?? ''));
$code = trim((string) ($body['code'] ?? ''));
$newPassword = (string) ($body['newPassword'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

if ($email === '' || $code === '' || $newPassword === '' || $confirmPassword === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}
if ($newPassword !== $confirmPassword) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
    exit;
}
if (strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

try {
    $authUser = $firebaseAuth->getUserByEmail($email);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $GENERIC_ERROR]);
    exit;
}

$userDoc = $firestore->getDocument('users', $authUser->uid);
$secret = $userDoc['twoFactorSecret'] ?? '';
if ($userDoc === null || !($userDoc['twoFactorEnabled'] ?? false) || $secret === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $GENERIC_ERROR]);
    exit;
}

$google2fa = new Google2FA();
$valid = false;
try {
    $valid = $google2fa->verifyKey($secret, $code);
} catch (\Throwable $e) {
    $valid = false;
}
if (!$valid) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $GENERIC_ERROR]);
    exit;
}

// Identity is now genuinely proven — failures past this point are real
// and safe to describe specifically, same as every other authenticated
// password-change flow in this app.
try {
    $firebaseAuth->changeUserPassword($authUser->uid, $newPassword);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not reset your password. Please choose a different password and try again.']);
    exit;
}

hc_revoke_all_remember_tokens_for_uid($authUser->uid);

$actorRole = match ($userDoc['role'] ?? null) {
    'admin' => 'System Administrator',
    'subadmin' => 'Sub-Administrator',
    'healthcare_staff' => 'Healthcare Staff',
    'pharmacist' => 'Pharmacist',
    'doctor' => 'Doctor',
    'medical_technician' => 'Medical Technologist',
    'radio_technologist' => 'Radio Technologist',
    default => 'System',
};
$firestore->addDocument('audit_logs', [
    'actorUid' => $authUser->uid,
    'actorName' => (string) ($userDoc['name'] ?? 'Unknown'),
    'actorRole' => $actorRole,
    'action' => 'updated',
    'module' => 'Account Security',
    'details' => 'Password reset via authenticator code for "' . ($userDoc['name'] ?? 'Unknown') . '" — all "remember me" sessions for this account were revoked.',
    'status' => 'success',
    'timestamp' => date('c'),
]);

echo json_encode(['success' => true]);
