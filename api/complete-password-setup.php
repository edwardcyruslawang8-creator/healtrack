<?php
/**
 * =====================================================================
 * Complete Password Setup (api/complete-password-setup.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/set-password.js, only reachable from
 * set-password.php, which only exists because api/session-login.php
 * returned {requiresPasswordSetup: true} and stashed
 * $_SESSION['pendingPasswordSetupUid'] server-side (same pattern as
 * $_SESSION['pending2faUid'] — see api/verify-2fa.php's header
 * comment). This endpoint reads THAT, never anything from the request
 * body, so a password can only ever be set on the account that just
 * passed Firebase Authentication a moment ago.
 *
 * $firebaseAuth->changeUserPassword() (Admin SDK) ADDS a password
 * credential to this account's existing Firebase Auth record — it does
 * not replace or remove the Google sign-in method, so afterward the
 * account can sign in either way. Once complete, this finishes the
 * session exactly the way api/session-login.php / api/verify-2fa.php
 * do (same fields, same admin/subadmin audit-log line) — see those files' own comments for
 * why each field is set.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$uid = $_SESSION['pendingPasswordSetupUid'] ?? '';
if ($uid === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nothing to set up. Please sign in again.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

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

$userDoc = $firestore->getDocument('users', $uid);
if ($userDoc === null) {
    unset($_SESSION['pendingPasswordSetupUid']);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'This account no longer exists. Please sign in again.']);
    exit;
}

try {
    $firebaseAuth->changeUserPassword($uid, $password);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not set your password. Please try again.']);
    exit;
}

unset($_SESSION['pendingPasswordSetupUid']);
session_regenerate_id(true);

$email = $userDoc['email'] ?? '';
$role = $userDoc['role'] ?? null;
$name = $userDoc['name'] ?? ucfirst(strstr($email, '@', true) ?: $email);

$_SESSION['uid'] = $uid;
$_SESSION['email'] = $email;
$_SESSION['name'] = $name;
$_SESSION['role'] = $role;
$_SESSION['theme'] = $userDoc['theme'] ?? 'Light';
$_SESSION['language'] = $userDoc['language'] ?? 'English';
$_SESSION['ntfReadIds'] = $userDoc['notificationsReadIds'] ?? [];
$_SESSION['ntfDismissedIds'] = $userDoc['notificationsDismissedIds'] ?? [];
try {
    $_SESSION['emailVerified'] = $firebaseAuth->getUser($uid)->emailVerified;
} catch (\Throwable $e) {
    $_SESSION['emailVerified'] = false;
}

if (in_array($role, ['admin', 'subadmin'], true)) {
    write_audit_log('logged_in', 'User Management', 'User logged in to the system.');
}

echo json_encode(['success' => true, 'role' => $role]);
