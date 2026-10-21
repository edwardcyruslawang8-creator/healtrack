<?php
/**
 * =====================================================================
 * Archive Notifications (api/archive-notifications.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/notifications.js when a notification's "Archive"
 * button is clicked (subadmin/, medical_technician/, pharmacist/,
 * doctor/, and medical-staff/'s notifications.php — all five load that
 * JS file as-is). This reuses the exact mechanism the old Delete action
 * used ($_SESSION['ntfDismissedIds'] + users/{uid}'s
 * notificationsDismissedIds) — same "no real notifications collection
 * to delete a row FROM" reasoning: for a role like pharmacist, a
 * "notification" is really a live-derived low-stock alert, not a
 * stored document, so there's nothing to actually remove. What's
 * different from the old Delete behavior: each role's own
 * notifications_*_firestore.php data file now KEEPS an archived
 * notification in $notifications (tagged 'archived' => true) instead
 * of excluding it, so the Archived tab has something to show —
 * api/unarchive-notifications.php is the real way back.
 *
 * config/constants.php's hc_*_unread_notification_count() functions
 * already exclude $_SESSION['ntfDismissedIds'] from their count (that
 * logic predates this file and needed no changes), so archiving an
 * unread notification also quietly stops it nagging the sidebar/bell
 * badge — the same side effect the old Delete action had.
 *
 * Body: {"ids": [1, 2, ...]}.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore

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
$archivedIds = $_SESSION['ntfDismissedIds'] ?? [];

if (!empty($body['ids']) && is_array($body['ids'])) {
    foreach ($body['ids'] as $id) {
        $id = (int) $id;
        if (!in_array($id, $archivedIds, true)) {
            $archivedIds[] = $id;
        }
    }
}

$_SESSION['ntfDismissedIds'] = $archivedIds;

$uid = $_SESSION['uid'] ?? '';
if ($uid !== '') {
    $existing = $firestore->getDocument('users', $uid) ?? [];
    $existing['notificationsDismissedIds'] = $archivedIds;
    $firestore->setDocument('users', $uid, $existing);
}

echo json_encode(['success' => true]);
