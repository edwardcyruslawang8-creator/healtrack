<?php
/**
 * =====================================================================
 * Unlock Sub-Administrator (api/unlock-subadmin.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/subadmin-actions.js when an admin confirms
 * unlocking a row from admin/subadmin_management.php's table. The
 * System-Administrator-side counterpart of api/unlock-user.php: a
 * sub-admin's own account can only be unlocked by an admin, never by
 * another sub-admin — subadmin/user_management.php deliberately hides
 * its Unlock action for subadmin-role rows for exactly this reason (see
 * that page's own comment).
 *
 * Clears status back to "active" and resets the failed-attempt
 * counter/lockout fields (includes/shared/login_lockout.php) so the
 * account starts clean — same fields api/unlock-user.php resets.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'admin') {
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
$uid = trim((string) ($body['id'] ?? ''));

if ($uid === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing sub-administrator id.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null || ($existing['role'] ?? null) !== 'subadmin') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sub-administrator not found.']);
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

write_audit_log('updated', 'Sub-Administrator Management', 'Unlocked sub-administrator "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
