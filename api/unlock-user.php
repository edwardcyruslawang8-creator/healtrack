<?php
/**
 * =====================================================================
 * Unlock User (api/unlock-user.php)
 * ---------------------------------------------------------------------
 * Called by subadmin/user_management.php's unlock-confirmation modal
 * ("Unlock Account" button) — the only way a locked account (status=
 * "locked", set either automatically by includes/shared/login_lockout.php
 * after 7 failed sign-in attempts, or manually via api/lock-user.php)
 * ever becomes usable again; a correct password alone no longer does
 * once status="locked". Clears status back to "active" and resets the
 * failed-attempt counter/lockout fields so the account starts clean.
 *
 * Same facility guardrail as api/suspend-user.php/api/lock-user.php: a
 * sub-admin can only unlock accounts at their own real facility,
 * resolved server-side — never trusts the client-supplied uid alone.
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

// A sub-admin's own account can only be unlocked by an admin
// (api/unlock-subadmin.php, admin/subadmin_management.php) — never by
// a peer sub-admin. subadmin/user_management.php already hides the
// Unlock button for a subadmin-role row for this same reason; this is
// the real boundary, not just the UI.
if (($existing['role'] ?? '') === 'subadmin') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

// Never trust the client-supplied uid alone — it must belong to this
// sub-admin's own facility, resolved server-side from their own
// users/{uid} doc, same as every other facility-scoped write in this app.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myFacility = $myDoc['facility'] ?? null;
if ($myFacility === null || ($existing['facility'] ?? '') !== $myFacility) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account not found.']);
    exit;
}

if (($existing['status'] ?? '') !== 'locked') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This account is not locked.']);
    exit;
}

$updated = array_merge($existing, [
    'status' => 'active',
    'failedLoginAttempts' => 0,
    'lockedUntil' => null,
    'lockedReason' => null,
    'lockedAt' => null,
]);

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not unlock this account. Please try again.']);
    exit;
}

write_audit_log('updated', 'User Management', 'Unlocked account "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
