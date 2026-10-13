<?php
/**
 * =====================================================================
 * Suspend User (api/suspend-user.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's suspend-confirmation modal
 * ("Suspend Account" button) — replaces the Delete action that used to
 * be here (api/delete-user.php, removed): a sub-admin can no longer
 * delete a user account at all, only put a reversible hold on it.
 * Sets status="inactive", which api/session-login.php now refuses to
 * start a session for (same real access-control boundary as the
 * "pending"/"locked" gates there) — not just a cosmetic badge change.
 * api/reactivate-user.php is the only way back to "active".
 *
 * Same guardrails as api/unlock-user.php: a sub-admin can't act on
 * their own account (would lock them out mid-session), a peer
 * sub-admin's account (only an admin manages those, via
 * admin/subadmin_management.php), or an account outside their own
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
    echo json_encode(['success' => false, 'error' => 'You cannot suspend your own account.']);
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
    echo json_encode(['success' => false, 'error' => 'Only an active account can be suspended.']);
    exit;
}

$updated = array_merge($existing, [
    'status' => 'inactive',
    'suspendedAt' => date('c'),
]);

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not suspend this account. Please try again.']);
    exit;
}

write_audit_log('updated', 'User Management', 'Suspended account "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
