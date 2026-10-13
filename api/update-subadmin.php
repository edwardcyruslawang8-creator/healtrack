<?php
/**
 * =====================================================================
 * Update Sub-Administrator (api/update-subadmin.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/subadmin-actions.js when an admin submits the
 * "Edit Sub-Administrator" modal (includes/subadmin_view_edit_modals.php).
 * Edits name, assigned facility, role, and status — not email/password,
 * which aren't exposed in that form.
 *
 * FirestoreRestClient::setDocument() overwrites the whole document (it's
 * a PATCH with no updateMask, which Firestore's REST API treats as a
 * full replace), so this fetches the existing doc first and merges the
 * edited fields into it rather than posting a partial field set — a
 * naive partial POST would silently drop email/createdAt/etc.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/admin/facility_management_firestore.php'; // $facilities, for facilityId -> name/location lookup
require_once __DIR__ . '/../includes/shared/audit_log_writer.php';         // write_audit_log()

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
$name = trim((string) ($body['name'] ?? ''));
$facilityId = trim((string) ($body['facilityId'] ?? ''));
$role = trim((string) ($body['role'] ?? ''));
$status = trim((string) ($body['status'] ?? ''));

if ($uid === '' || $name === '' || $facilityId === '' || $role === '' || $status === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every field.']);
    exit;
}

$validStatuses = ['active', 'inactive', 'locked', 'pending'];
if (!in_array($status, $validStatuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

$facility = null;
foreach ($facilities as $f) {
    if ($f['id'] === $facilityId) {
        $facility = $f;
        break;
    }
}
if ($facility === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a valid facility.']);
    exit;
}

$existing = $firestore->getDocument('users', $uid);
if ($existing === null || ($existing['role'] ?? null) !== 'subadmin') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sub-administrator not found.']);
    exit;
}

$updated = array_merge($existing, [
    'name' => $name,
    'facility' => $facility['name'],
    'facilityId' => $facility['id'],
    'facilityLocation' => $facility['location'],
    'subadminRole' => $role,
    'status' => $status,
]);

$saved = $firestore->setDocument('users', $uid, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save changes. Please try again.']);
    exit;
}

// Keep the Auth record's displayName in sync — non-fatal if it fails,
// since the Firestore profile (what this app actually reads) is saved.
try {
    $firebaseAuth->updateUser($uid, ['displayName' => $name]);
} catch (\Throwable $e) {
    error_log('[update-subadmin] Auth displayName sync failed for ' . $uid . ': ' . $e->getMessage());
}

write_audit_log('updated', 'Sub-Administrator Management', 'Updated sub-administrator "' . $name . '".');

echo json_encode(['success' => true]);
