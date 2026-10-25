<?php
/**
 * =====================================================================
 * Notifications (Radiologic Technologist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every row is a real notification generated live from
 * includes/firestore/lab_results.php's hc_get_pending_lab_xray_notifications(),
 * narrowed to X-ray only (this role never sees pending LABORATORY
 * referrals, which stay medical_technician's own) — one entry per
 * xray_results doc still "pending" (ordered but no result entered yet).
 * Modeled directly on
 * includes/medical_technician/notifications_medtech_firestore.php.
 *
 * Which of them are READ is real and persisted server-side —
 * api/mark-notifications-read.php -> $_SESSION['ntfReadIds'], which is
 * what config/constants.php's hc_radiotech_unread_notification_count() —
 * this page's tab badges, the sidebar nav badge, and the topbar bell
 * badge, on THIS page and every other radiologic_technologist/*.php page —
 * counts against.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_pending_lab_xray_notifications()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'X-ray', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, one row per pending X-ray exam.
// ---------------------------------------------------------------------
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
// Real gating — see includes/shared/notification_preferences.php's
// header comment. X-ray referrals don't map to any more specific
// category, so they're only gated by the 'system' master switch.
$rawNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? array_merge(
        array_filter([hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])]),
        array_filter([hc_get_phone_reminder_notification($myUid, $myDoc ?? [])]),
        array_values(array_filter(hc_get_pending_lab_xray_notifications(), fn ($n) => $n['type'] === 'X-ray'))
    )
    : [];
$notifications = array_map(function ($n) use ($ntfReadIds, $ntfArchivedIds) {
    $n['unread'] = !in_array($n['id'], $ntfReadIds, true);
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);

    return $n;
}, $rawNotifications);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side (see
// assets/js/notifications.js). "All Notifications" has no badge at
// all — a badge there went through two designs (mirroring the unread
// count, then a real total count) before landing on no number at all
// being the clearest option.
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_radiotech_unread_notification_count();
$notificationsArchivedCount = count(array_filter($notifications, fn ($n) => $n['archived']));

$notificationTabs = [
    ['key' => 'all',            'label' => 'All Notifications', 'active' => true],
    ['key' => 'unread',         'label' => 'Unread',            'badge' => $notificationsUnreadCount],
    ['key' => 'archived',       'label' => 'Archived',          'badge' => $notificationsArchivedCount],
];

// Pagination is client-side now (assets/js/notifications.js) — every
// notification renders into the table below, unsliced, and the browser
// shows/hides 8 at a time depending on which tab/filter/page is active
// (slicing here first used to mean a page's 8 rows didn't reliably
// match whatever tab was actually active — see that file's header
// comment). $notificationsTotal/$notificationsPerPage below are just
// the initial "Showing..." text's starting point.
$notificationsPerPage = 8;
$notificationsTotal = count($notifications);
