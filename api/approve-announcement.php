<?php
/**
 * =====================================================================
 * Approve Announcement (api/approve-announcement.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/admin-announcements.js when a System Administrator
 * clicks "Approve" on admin/announcements.php's Pending Approval queue.
 * Sets the announcement's real 'status' field to 'approved' — the
 * moment that happens, it starts showing up on patient/announcements.php
 * and index.php's "latest 3" widget (both filter to 'approved' only;
 * see firebase/firestore.php's getAllAnnouncements()/getAnnouncements()).
 *
 * setDocument() PATCHes without an updateMask, which replaces the WHOLE
 * doc with only the fields passed in — the existing doc is read first
 * and written back with just 'status' changed, same pattern
 * api/update-profile-settings.php already uses.
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

$existing['status'] = 'approved';
$ok = $firestore->setDocument('announcements', $id, $existing);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not approve this announcement. Please try again.']);
    exit;
}

write_audit_log('approved', 'Announcements', 'Approved announcement "' . ($existing['title'] ?? '') . '" — now visible to patients.');

echo json_encode(['success' => true]);
