<?php
/**
 * =====================================================================
 * Reactivate User (api/reactivate-user.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's reactivate-confirmation
 * modal ("Reactivate Account" button) — the only way a suspended
 * account (status="inactive", api/suspend-user.php) becomes usable
 * again; a correct password alone no longer does once suspended, same
 * reasoning as api/unlock-user.php for a locked account.
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

if (($existing['status'] ?? '') !== 'inactive') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This account is not suspended.']);
    exit;
}

$updated = array_merge($existing, [
    'status' => 'active',
    'suspendedAt' => null,
]);

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not reactivate this account. Please try again.']);
    exit;
}

write_audit_log('updated', 'User Management', 'Reactivated account "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
