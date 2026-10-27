<?php
/**
 * =====================================================================
 * Update Sub-Administrator Status (api/update-subadmin-status.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/subadmin-actions.js when an admin flips a sub-
 * administrator's "Active" switch on the "Manage Account" modal
 * (admin/subadmin_management.php). Updates ONLY the real
 * users/{uid} doc's status field — never username/email/facility/role
 * (there's no general Edit Sub-Administrator feature any more; see
 * includes/admin/subadmin_view_edit_modals.php's header comment).
 *
 * Only ever sets 'active' or 'inactive' — a "locked" or "pending"
 * account isn't this endpoint's to touch (the client-side toggle is
 * disabled for those too, but this is the real guard): Unlock Account
 * (api/unlock-subadmin.php) is the real path out of "locked" — it
 * resets the real lockout counter, which a plain status write here
 * wouldn't — and "pending" resolves itself once the invited account
 * actually signs in for the first time.
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
$status = trim((string) ($body['status'] ?? ''));

if ($uid === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing sub-administrator id.']);
    exit;
}
if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null || ($existing['role'] ?? null) !== 'subadmin') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sub-administrator not found.']);
    exit;
}

$currentStatus = $existing['status'] ?? 'active';
if (!in_array($currentStatus, ['active', 'inactive'], true)) {
    // Locked/pending — not this endpoint's to touch (see header comment).
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'This account is currently ' . $currentStatus . ' — use Unlock Account instead, or wait for the invitation to be accepted.']);
    exit;
}

$saved = $firestore->setDocument('users', $uid, array_merge($existing, [
    'status' => $status,
]));

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save. Please try again.']);
    exit;
}

$name = $existing['name'] ?? 'Sub-administrator';
write_audit_log('updated', 'Sub-Administrator Management', 'Set sub-administrator "' . $name . '" to ' . $status . '.');

echo json_encode(['success' => true, 'status' => $status]);
