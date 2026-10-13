<?php
/**
 * =====================================================================
 * Reject Announcement (api/reject-announcement.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/admin-announcements.js when a System Administrator
 * clicks "Reject" on admin/announcements.php's Pending Approval queue.
 * Sets the announcement's real 'status' field to 'rejected' — it's kept
 * (not deleted) so the sub-admin who posted it can still see it was
 * reviewed and rejected on their own subadmin/announcements.php, but it
 * never shows up on patient/announcements.php or index.php's "latest 3"
 * widget (both filter to 'approved' only).
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
$id = trim((string) ($body['id'] ?? ''));

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing announcement id.']);
    exit;
}

$existing = $firestore->getDocument('announcements', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Announcement not found.']);
    exit;
}

$existing['status'] = 'rejected';
$ok = $firestore->setDocument('announcements', $id, $existing);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not reject this announcement. Please try again.']);
    exit;
}

write_audit_log('rejected', 'Announcements', 'Rejected announcement "' . ($existing['title'] ?? '') . '".');

echo json_encode(['success' => true]);
