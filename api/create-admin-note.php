<?php
/**
 * =====================================================================
 * Create Admin Note (api/create-admin-note.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/admin-announcements.js when a System Administrator
 * submits admin/announcements.php's "Send a Note to Sub-Admins" form.
 * Writes a real doc to a new "admin_notes" Firestore collection —
 * deliberately separate from "announcements" (which is the public,
 * patient-facing, approval-gated collection): a note is a private,
 * internal admin -> sub-admin message that a patient must never see,
 * so keeping it in its own collection means the patient-facing read
 * paths (firebase/firestore.php's getAnnouncements()/getAllAnnouncements())
 * never need to know it exists at all, rather than relying on a filter
 * to keep it hidden.
 *
 * $facility is either 'All Facilities' (every sub-admin sees it) or one
 * real facility name from admin/announcements.php's own facility
 * picker — includes/subadmin/announcements_firestore.php reads this
 * collection back and shows a note when its facility is 'All
 * Facilities' OR matches the signed-in sub-admin's own real facility.
 *
 * $validFacilities is derived here the same way
 * includes/admin/announcements_firestore.php builds the picker itself
 * (real facility names that actually have a sub-administrator, not a
 * hardcoded 3-name list) — never trusting the client-submitted
 * $facility alone just because it looked valid in that dropdown.
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

$facilitiesWithSubadmins = [];
foreach ($firestore->listDocuments('users') as $doc) {
    $f = $doc['fields'];
    if (($f['role'] ?? '') === 'subadmin' && !empty($f['facility'])) {
        $facilitiesWithSubadmins[$f['facility']] = true;
    }
}
$validFacilities = array_merge(['All Facilities'], array_keys($facilitiesWithSubadmins));

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$title = trim((string) ($body['title'] ?? ''));
$message = trim((string) ($body['message'] ?? ''));
$facility = trim((string) ($body['facility'] ?? 'All Facilities'));

if ($title === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out both the title and message.']);
    exit;
}
if (!in_array($facility, $validFacilities, true)) {
    $facility = 'All Facilities';
}

$newId = $firestore->addDocument('admin_notes', [
    'title' => $title,
    'message' => $message,
    'facility' => $facility,
    'postedBy' => $_SESSION['name'] ?? 'System Administrator',
    'postedAt' => date('c'),
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not send this note. Please try again.']);
    exit;
}

write_audit_log('created', 'Announcements', 'Sent a note to ' . $facility . ': "' . $title . '".');

echo json_encode(['success' => true, 'id' => $newId]);
