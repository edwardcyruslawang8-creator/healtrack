<?php
/**
 * =====================================================================
 * Mark Notifications Read (api/mark-notifications-read.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/notifications.js (subadmin/, medical_technician/,
 * pharmacist/, doctor/, and medical-staff/'s notifications.php — all
 * five load this file as-is) whenever a notification is marked read —
 * opening its details modal, "Mark as read" (bulk, where that button
 * still exists), or "Mark all as read". Persists which ids have been
 * read into $_SESSION['ntfReadIds'] (so the unread count driving every
 * page's sidebar nav badge + topbar bell badge — see
 * config/constants.php's subadmin_unread_notification_count(),
 * hc_pharmacist_unread_notification_count(),
 * hc_doctor_unread_notification_count(),
 * hc_medstaff_unread_notification_count(), and
 * hc_medtech_unread_notification_count() — survives navigating to
 * another page) AND into the signed-in user's own users/{uid}
 * Firestore doc, so it also survives logging out and back in
 * (api/session-login.php reads it back into a fresh session on every
 * login).
 *
 * Body: {"ids": [1, 2, ...]} to mark specific ids read (opening a
 * single notification's details modal). {"all": true} — "Mark all as
 * read" — marks the COMPLETE real id universe for the signed-in role
 * read, computed server-side (hc_doctor_all_notification_ids() and its
 * five siblings in config/constants.php, one per role), NOT just
 * whichever ids the client happens to have in the DOM. This used to
 * send the client's currently-rendered row ids instead, which was
 * wrong the moment a role's notifications list grew past one
 * pagination page (assets/js/notifications.js's getRows() only ever
 * sees the current page) — "Mark all as read" would silently leave
 * every notification beyond page 1 still unread, so the sidebar/bell
 * badge kept showing a nonzero count on every other page even though
 * the user had just clicked "mark all as read". Mark-only either way
 * — there's no way to mark something unread again from here, matching
 * the page's own UI (no "mark as unread" action exists).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../config/constants.php'; // subadmin_unread_notification_count(), subadmin_seed_unread_notification_ids(), hc_pharmacist_unread_notification_count()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'medical_technician', 'radio_technologist', 'pharmacist', 'doctor', 'healthcare_staff'], true)) {
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
$readIds = $_SESSION['ntfReadIds'] ?? [];

// Which ids actually need marking read this request — the role's
// COMPLETE real id universe for {"all": true}, or just the specific
// ids given otherwise. Same role dispatch as the $unreadCount switch
// below (and must stay in sync with it — each role's own
// *_all_notification_ids() in config/constants.php is the single
// source of truth both read from).
if (!empty($body['all'])) {
    switch ($_SESSION['role'] ?? null) {
        case 'pharmacist':
            $idsToMark = hc_pharmacist_all_notification_ids();
            break;
        case 'doctor':
            $idsToMark = hc_doctor_all_notification_ids();
            break;
        case 'healthcare_staff':
            $idsToMark = hc_medstaff_all_notification_ids();
            break;
        case 'medical_technician':
            $idsToMark = hc_medtech_all_notification_ids();
            break;
        case 'radio_technologist':
            $idsToMark = hc_radiotech_all_notification_ids();
            break;
        default:
            $idsToMark = subadmin_all_notification_ids();
    }
} elseif (!empty($body['ids']) && is_array($body['ids'])) {
    $idsToMark = array_map('intval', $body['ids']);
} else {
    $idsToMark = [];
}

foreach ($idsToMark as $id) {
    $id = (int) $id;
    if (!in_array($id, $readIds, true)) {
        $readIds[] = $id;
    }
}

$_SESSION['ntfReadIds'] = $readIds;

// Also persist to this subadmin's own Firestore user doc so read-state
// survives logging out and back in, not just navigating between pages
// within the same session — api/session-login.php reads this field
// back into a fresh session on the next login. setDocument() PATCHes
// without an updateMask, which replaces the WHOLE doc with only the
// fields passed in, so the existing doc is read first and written back
// with just notificationsReadIds changed (same pattern as
// api/update-profile-settings.php).
$uid = $_SESSION['uid'] ?? '';
if ($uid !== '') {
    $existing = $firestore->getDocument('users', $uid) ?? [];
    $existing['notificationsReadIds'] = $readIds;
    $firestore->setDocument('users', $uid, $existing);
}

// notifications.js never actually reads this field (it recomputes the
// count client-side from the DOM instead), but the role-appropriate
// count is returned anyway for API correctness.
switch ($_SESSION['role'] ?? null) {
    case 'pharmacist':
        $unreadCount = hc_pharmacist_unread_notification_count();
        break;
    case 'doctor':
        $unreadCount = hc_doctor_unread_notification_count();
        break;
    case 'healthcare_staff':
        $unreadCount = hc_medstaff_unread_notification_count();
        break;
    case 'medical_technician':
        $unreadCount = hc_medtech_unread_notification_count();
        break;
    case 'radio_technologist':
        $unreadCount = hc_radiotech_unread_notification_count();
        break;
    default:
        $unreadCount = subadmin_unread_notification_count();
}

echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
