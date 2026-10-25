<?php
/**
 * =====================================================================
 * Notifications (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every row is a real notification generated live from
 * includes/firestore/lab_results.php's hc_get_pending_lab_notifications()
 * (the Laboratory-only slice — X-ray referrals are radio_technologist's
 * own queue now): one entry per lab_results doc still "pending" (ordered
 * but no result entered yet). This replaces
 * the previous fully-fabricated 8-row placeholder list (specimen
 * pickups, equipment calibration, etc. — none of which reflect
 * anything real for this role) with the one notification-worthy real
 * data source that actually exists for a medical technologist right now:
 * their own pending test queue. With this app's current seed data
 * (every real lab/x-ray result is already "completed") this list will
 * likely be empty right now — genuinely, not a bug; see
 * includes/firestore/lab_results.php's header comment.
 *
 * Which of them are READ is real and persisted server-side —
 * api/mark-notifications-read.php -> $_SESSION['ntfReadIds'], which is
 * what config/constants.php's hc_medtech_unread_notification_count() —
 * this page's tab badges, the sidebar nav badge, and the topbar bell
 * badge, on THIS page and every other medical_technician/*.php page —
 * counts against. Replaces the previous reuse of
 * subadmin_unread_notification_count() (a coincidental fit against a
 * fixed 1-5 seed list, not this role's own real data). There's no
 * Delete action any more — medical_technician/notifications.php's
 * Actions column is a "View Details" button plus a real
 * Archive/Unarchive action (api/archive-notifications.php /
 * api/unarchive-notifications.php), using that exact same
 * $_SESSION['ntfDismissedIds'] mechanism, now repurposed for archiving
 * (see $notifications below).
 *
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_pending_lab_notifications()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'Laboratory', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, one row per pending lab/x-ray result.
// Archived ones (api/archive-notifications.php) STAY in this list,
// tagged 'archived' => true, rather than being excluded — the Archived
// tab needs them to show something; every other tab hides an archived
// row client-side instead (see assets/js/notifications.js). 'unread'
// wins from real session-persisted read state over the default
// "everything starts unread" assumption for a notification not yet
// marked read.
// ---------------------------------------------------------------------
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
// Real gating — see includes/shared/notification_preferences.php's
// header comment. Lab/x-ray referrals don't map to any more specific
// category, so they're only gated by the 'system' master switch.
$rawNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? array_merge(
        array_filter([hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])]),
        array_filter([hc_get_phone_reminder_notification($myUid, $myDoc ?? [])]),
        hc_get_pending_lab_notifications()
    )
    : [];
$notifications = array_map(function ($n) use ($ntfReadIds, $ntfArchivedIds) {
    $n['unread'] = !in_array($n['id'], $ntfReadIds, true);
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);

    return $n;
}, $rawNotifications);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived']. Built from $notifications
// (after the archived-tagging above) so the Archived badge counts the
// real total, not just one page's rows. "All Notifications" has no
// badge at all — a badge there went through two designs (mirroring the
// unread count, then a real total count) before landing on no number
// at all being the clearest option.
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_medtech_unread_notification_count();
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
