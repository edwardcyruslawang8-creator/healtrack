<?php
/**
 * =====================================================================
 * Disable 2FA (api/disable-2fa.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/two-fa.js when the user confirms "Disable 2FA"
 * on their Profile / Settings page. Re-verifies the CURRENT password
 * first (same Identity Toolkit signInWithPassword pattern
 * api/update-admin-password.php already uses — copied here rather than
 * refactored out, to keep that file's own behavior untouched) before
 * clearing anything: a hijacked-but-still-logged-in browser session
 * shouldn't be able to turn off 2FA on its own.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
$email = $_SESSION['email'] ?? '';
if ($uid === '' || $email === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Your session has expired. Please sign in again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$currentPassword = (string) ($body['currentPassword'] ?? '');

if ($currentPassword === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter your current password.']);
    exit;
}

$identityToolkit = new Client(['timeout' => 8]);
try {
    $identityToolkit->post('https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword', [
        'query' => ['key' => FIREBASE_WEB_API_KEY],
        'json' => ['email' => $email, 'password' => $currentPassword, 'returnSecureToken' => true],
    ]);
} catch (RequestException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Your current password is incorrect.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid) ?? [];
unset($existing['twoFactorEnabled'], $existing['twoFactorSecret'], $existing['twoFactorEnabledAt']);
$saved = $firestore->setDocument('users', $uid, $existing);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not disable two-factor authentication. Please try again.']);
    exit;
}

write_audit_log('updated', 'Profile / Settings', 'Disabled two-factor authentication.');

echo json_encode(['success' => true]);
