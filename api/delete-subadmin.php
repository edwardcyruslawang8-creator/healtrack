<?php
/**
 * =====================================================================
 * Delete Sub-Administrator (api/delete-subadmin.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/subadmin-actions.js when an admin confirms
 * deleting a row from admin/subadmin_management.php's table. Removes
 * both the Firestore users/{uid} profile document and the underlying
 * Firebase Auth account, mirroring how api/create-subadmin.php creates
 * both together.
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

$firestoreDeleted = $firestore->deleteDocument('users', $uid);

if (!$firestoreDeleted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not delete the account. Please try again.']);
    exit;
}

// The Firestore profile (what the table reads) is already gone either
// way — log a failure here so a stray Auth account can be cleaned up
// manually rather than failing the whole request.
try {
    $firebaseAuth->deleteUser($uid);
} catch (\Throwable $e) {
    error_log('[delete-subadmin] Auth deleteUser failed for ' . $uid . ': ' . $e->getMessage());
}

write_audit_log('deleted', 'Sub-Administrator Management', 'Deleted sub-administrator "' . ($existing['name'] ?? $uid) . '".');

echo json_encode(['success' => true]);
