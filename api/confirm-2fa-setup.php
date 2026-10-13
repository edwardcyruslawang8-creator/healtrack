<?php
/**
 * =====================================================================
 * Confirm 2FA Setup (api/confirm-2fa-setup.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/two-fa.js when the user submits the 6-digit code
 * from their authenticator app in the "Enable 2FA" modal, proving they
 * actually scanned/configured the secret api/generate-2fa-secret.php
 * just handed back (that endpoint never persisted it — this is the
 * first point anything gets written).
 *
 * On a valid code: writes twoFactorEnabled/twoFactorSecret/
 * twoFactorEnabledAt to users/{uid} — from this point on,
 * api/session-login.php will require a code on every future sign-in
 * (see that file's header comment).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

use PragmaRX\Google2FA\Google2FA;

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['admin', 'subadmin', 'healthcare_staff', 'pharmacist', 'medical_technician', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['uid'] ?? '';
if ($uid === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$secret = trim((string) ($body['secret'] ?? ''));
$code = trim((string) ($body['code'] ?? ''));

if ($secret === '' || $code === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit code from your authenticator app.']);
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
    echo json_encode(['success' => false, 'error' => 'That code is incorrect or has expired. Please try again.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid) ?? [];
$saved = $firestore->setDocument('users', $uid, array_merge($existing, [
    'twoFactorEnabled' => true,
    'twoFactorSecret' => $secret,
    'twoFactorEnabledAt' => date('c'),
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not enable two-factor authentication. Please try again.']);
    exit;
}

write_audit_log('updated', 'Profile / Settings', 'Enabled two-factor authentication.');

echo json_encode(['success' => true]);
