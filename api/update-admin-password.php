<?php
/**
 * =====================================================================
 * Update Account Password (api/update-admin-password.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/profile-settings.js / assets/js/subadmin-profile-settings.js
 * when the signed-in user submits the "Password Change" form on
 * admin/profile_settings.php, subadmin/profile_settings.php,
 * medical-staff/profile_settings.php, or pharmacist/profile_settings.php.
 * Despite the filename (kept for git-history continuity — this
 * predates the sub-admin, medical-staff, and pharmacist pages),
 * nothing here branches on role; it's generic by uid.
 *
 * The Firebase Admin SDK can set a new password directly, but it has no
 * "verify this password is correct" call (that's a client-side Auth
 * concern) — so the current password is verified server-side first by
 * calling Identity Toolkit's accounts:signInWithPassword REST endpoint
 * with it (this only checks the password; it doesn't disturb the
 * browser's existing session/cookies). Only once that succeeds does
 * this call changeUserPassword() with the new one.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/account_password_helpers.php'; // hc_account_has_password()

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
$newPassword = (string) ($body['newPassword'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

// A "Sign up with Google" account has no password yet (set-password.php
// normally catches this at login, but an already-active session
// predating that gate, or one that just hasn't re-logged-in, could
// still reach this form) — there's nothing to verify in that case, so
// "Current Password" isn't required, matching what the UI now shows.
$hasPassword = hc_account_has_password($uid);

if (($hasPassword && $currentPassword === '') || $newPassword === '') {
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
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
    exit;
}

if ($hasPassword) {
    // Verify the current password by attempting a real sign-in with it.
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
}

try {
    $firebaseAuth->changeUserPassword($uid, $newPassword);
} catch (\Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Could not update your password: ' . $e->getMessage()]);
    exit;
}

$existing = $firestore->getDocument('users', $uid) ?? [];
$firestore->setDocument('users', $uid, array_merge($existing, ['passwordChangedAt' => date('c')]));

write_audit_log('updated', 'Profile / Settings', 'Changed account password.');

echo json_encode(['success' => true]);
