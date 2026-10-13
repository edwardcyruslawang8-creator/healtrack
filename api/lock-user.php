<?php
/**
 * =====================================================================
 * Lock User (api/lock-user.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's lock-confirmation modal
 * ("Lock Account" button) — a MANUAL counterpart to the automatic
 * permanent lockout includes/shared/login_lockout.php already applies
 * after 7 failed sign-in attempts. Both set the exact same
 * status="locked", so api/session-login.php's existing lockout gate
 * (hc_login_lockout_status()) refuses a session for this account
 * either way, with no changes needed there — it already treats
 * status="locked" as permanent regardless of how it got set. Only
 * api/unlock-user.php clears it, same as the automatic case.
 *
 * Same guardrails as api/unlock-user.php/api/suspend-user.php: a
 * sub-admin can't act on their own account, a peer sub-admin's account
 * (only an admin manages those), or an account outside their own
 * facility — resolved server-side, never trusting the client-supplied
 * uid alone.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$uid = trim((string) ($body['uid'] ?? ''));

if ($uid === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing account id.']);
    exit;
}
if ($uid === ($_SESSION['uid'] ?? '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'You cannot lock your own account.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

if (($existing['role'] ?? '') === 'subadmin') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;
if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

if (($existing['status'] ?? 'active') !== 'active') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only an active account can be locked.']);
    exit;
}

$updated = array_merge($existing, [
    'status' => 'locked',
    'lockedUntil' => null,
    'lockedReason' => 'Manually locked by sub-administrator',
    'lockedAt' => date('c'),
]);

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not lock this account. Please try again.']);
    exit;
}

write_audit_log('updated', 'User Management', 'Locked account "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
