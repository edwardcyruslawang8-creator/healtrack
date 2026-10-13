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
 * Body: {"ids": [1, 2, ...]} to mark specific ids read — this is what
 * every caller actually sends now, including "Mark all as read" (the
 * client gathers every currently-visible row's real id rather than
 * relying on this endpoint to guess a fixed seed list, since
 * pharmacist's real notification ids are generated live from current
 * inventory, not a static set like subadmin's). {"all": true} still
 * works as a fallback (marks subadmin_seed_unread_notification_ids()
 * read) for backward compatibility, but nothing calls it anymore.
 * Mark-only either way — there's no way to mark something unread again
 * from here, matching the page's own UI (no "mark as unread" action
 * exists).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../config/constants.php'; // subadmin_unread_notification_count(), subadmin_seed_unread_notification_ids(), hc_pharmacist_unread_notification_count()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'medical_technician', 'pharmacist', 'doctor', 'healthcare_staff'], true)) {
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

if (!empty($body['all'])) {
    $readIds = subadmin_seed_unread_notification_ids();
} elseif (!empty($body['ids']) && is_array($body['ids'])) {
    foreach ($body['ids'] as $id) {
        $id = (int) $id;
        if (!in_array($id, $readIds, true)) {
            $readIds[] = $id;
        }
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
    default:
        $unreadCount = subadmin_unread_notification_count();
}

echo json_encode(['success' => true, 'unreadCount' => $unreadCount]);
