<?php
/**
 * =====================================================================
 * Create Announcement (api/create-announcement.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/announcements_subadmin.js when a sub-admin
 * submits subadmin/announcements.php's "+ New Announcement" modal
 * (includes/subadmin/add_announcement_modal.php). Writes a real doc to
 * the same "announcements" Firestore collection firebase/firestore.php's
 * getAllAnnouncements()/getAnnouncements() already read — always
 * stamped 'status' => 'pending' here, so it does NOT show up on
 * patient/announcements.php or index.php's "latest 3" widget (both
 * only ever show 'approved' rows) until a System Administrator approves
 * it from admin/announcements.php (api/approve-announcement.php /
 * api/reject-announcement.php). It's still visible to the posting
 * sub-admin right away, on their own subadmin/announcements.php, with
 * its real Pending/Approved/Rejected status shown.
 *
 * icon/color are derived server-side from category (never trusted from
 * the client) so every announcement in a given category looks visually
 * consistent — same "General/Alert/Event" -> green/red/blue mapping
 * includes/public/announcements-data.php's $categoryMeta already uses
 * for the category tag itself.
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

// category -> icon/color, matching includes/public/announcements-data.php's
// $categoryMeta labels/colors exactly.
$categoryIconMeta = [
    'general' => ['icon' => 'bi-megaphone-fill',        'color' => 'green'],
    'alert'   => ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'red'],
    'event'   => ['icon' => 'bi-calendar-event-fill',   'color' => 'blue'],
];

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$title = trim((string) ($body['title'] ?? ''));
$desc = trim((string) ($body['desc'] ?? ''));
$category = trim((string) ($body['category'] ?? 'general'));
$important = (bool) ($body['important'] ?? false);

if ($title === '' || $desc === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out both the title and description.']);
    exit;
}
if (!isset($categoryIconMeta[$category])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid category.']);
    exit;
}

$meta = $categoryIconMeta[$category];

$newId = $firestore->addDocument('announcements', [
    'title' => $title,
    'desc' => $desc,
    'category' => $category,
    'important' => $important,
    'icon' => $meta['icon'],
    'color' => $meta['color'],
    'postedBy' => $_SESSION['name'] ?? 'Sub-Administrator',
    'postedAt' => date('c'),
    'status' => 'pending',
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not post this announcement. Please try again.']);
    exit;
}

write_audit_log('created', 'Announcements', 'Submitted announcement "' . $title . '" (' . $category . ') for admin approval.');

echo json_encode(['success' => true, 'id' => $newId]);
