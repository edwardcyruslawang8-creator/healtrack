<?php
/**
 * =====================================================================
 * Notifications — Placeholder Data
 * ---------------------------------------------------------------------
 * Static stand-ins for subadmin/notifications.php, following the same
 * per-page "-data.php" convention as the rest of the app. This is a
 * top-level Sub-Admin section (its own sidebar nav item), so it
 * derives $adminProfile/$facilityOptions the same way
 * includes/user_management_firestore.php and its siblings do.
 *
 * FRONT-END ONLY — there's no notifications collection anywhere in
 * this app yet. $adminProfile IS real though — read from the
 * signed-in sub-admin's own Firestore users/{uid} document.
 *
 * Pagination is real over these 8 seed rows (1 page) — it used to be
 * hardcoded to a fake 6 pages purely to match a reference design's
 * mockup, but a control implying 5 more pages of notifications that
 * don't exist is itself a dead button, so it's honest now instead.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'Patient', 'Inventory', 'System', 'Reminder', 'Stock', 'User'];

// ---------------------------------------------------------------------
// Notifications List — Future: SELECT ... FROM notifications
// Only the first page (8 of 24) is seeded here; $notificationsTotal
// reflects the full count so pagination renders correctly. 'unread' is
// an arbitrary flag on the first 5 of these 8 rows so the "Unread" tab
// filter and the badge counters have something real to demonstrate
// against.
// ---------------------------------------------------------------------
$notifications = [
    ['id' => 1, 'title' => 'New Patient Registered',   'description' => 'Juan Dela Cruz has been registered as a new patient.',                 'type' => 'Patient',           'date' => 'May 20, 2026', 'time' => '09:15 AM', 'icon' => 'bi-person-plus-fill',       'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'unread' => true],
    ['id' => 2, 'title' => 'Low Stock Alert',           'description' => 'Paracetamol 500mg Tablet is running low in stock.',                     'type' => 'Inventory', 'date' => 'May 20, 2026', 'time' => '08:45 AM', 'icon' => 'bi-capsule',                'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'unread' => true],
    ['id' => 3, 'title' => 'Consultation Log Updated',  'description' => 'A consultation log was updated by Ralph Mendoza.',                      'type' => 'System',             'date' => 'May 20, 2026', 'time' => '08:30 AM', 'icon' => 'bi-clipboard2-pulse-fill',  'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'unread' => true],
    ['id' => 4, 'title' => 'Appointment Reminder',      'description' => 'You have 3 scheduled appointments today.',                              'type' => 'Reminder',      'date' => 'May 20, 2026', 'time' => '07:00 AM', 'icon' => 'bi-calendar-check-fill',    'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'unread' => true],
    ['id' => 5, 'title' => 'System Update',             'description' => 'System maintenance will be on May 22, 2026 from 10:00 PM to 2:00 AM.',  'type' => 'System',       'date' => 'May 19, 2026', 'time' => '06:15 PM', 'icon' => 'bi-shield-fill-check',      'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'unread' => true],
    ['id' => 6, 'title' => 'Stock Request Approved',    'description' => 'Your stock request REQ-2026-00025 has been approved.',                 'type' => 'Stock',           'date' => 'May 19, 2026', 'time' => '04:25 PM', 'icon' => 'bi-cart-fill',              'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'unread' => false],
    ['id' => 7, 'title' => 'New User Added',            'description' => 'Liza Reyes has been added as a new Pharmacist.',                        'type' => 'User',               'date' => 'May 19, 2026', 'time' => '03:10 PM', 'icon' => 'bi-people-fill',            'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'unread' => false],
    ['id' => 8, 'title' => 'Expired Medicine Alert',    'description' => 'Ibuprofen 400mg Tablet (Batch IB400-0425) is expired.',                'type' => 'Inventory',   'date' => 'May 19, 2026', 'time' => '10:05 AM', 'icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'unread' => false],
];

// The real 2FA reminder (see config/constants.php's
// subadmin_unread_notification_count(), which already counts this toward
// the sidebar/bell/tab badges) needs a real row here too — otherwise it's
// counted but never shown, so a sub-admin without 2FA enabled can never
// get the badge below 1 no matter what they do on this page. Prepended,
// not appended: this page's 8 seed rows already fill page 1 on their
// own, so appending would silently bury this behind pagination instead
// of actually being visible/actionable.
// Gated by 'system' (master "Receive in-app notifications" toggle) —
// see includes/shared/notification_preferences.php's header comment.
// The 8 rows above are fake seed data (not real sources), so this is
// the only one real thing on this page worth gating.
$twoFaNotification = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])
    : null;
if ($twoFaNotification !== null) {
    $twoFaNotification['unread'] = true;
    array_unshift($notifications, $twoFaNotification);
}

// Real, session-persisted read state (see api/mark-notifications-read.php
// and config/constants.php's subadmin_unread_notification_count()) wins
// over each row's hardcoded seed 'unread' — so a notification marked read
// stays marked read on reload/navigation instead of resetting to the
// seed data every page load. Same for 'archived' (api/archive-notifications.php)
// — tagged here rather than excluded, so the Archived tab below has
// something to show; every other tab hides an archived row client-side
// instead (see assets/js/notifications.js).
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
foreach ($notifications as &$n) {
    if (in_array($n['id'], $ntfReadIds, true)) {
        $n['unread'] = false;
    }
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);
}
unset($n);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side now (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived']. The "all"/"unread" badges
// use the same session-backed count as every page's sidebar/topbar
// badges (config/constants.php) rather than a local recount, so they
// agree with each other on first paint; the Archived badge is a real
// count of $notifications (built just above, before pagination below)
// so it reflects the real total, not just this page's 8 rows.
// ---------------------------------------------------------------------
$notificationsUnreadCount = subadmin_unread_notification_count();
$notificationsArchivedCount = count(array_filter($notifications, fn ($n) => $n['archived']));

$notificationTabs = [
    ['key' => 'all',            'label' => 'All Notifications', 'badge' => $notificationsUnreadCount, 'active' => true],
    ['key' => 'unread',         'label' => 'Unread',            'badge' => $notificationsUnreadCount],
    ['key' => 'archived',       'label' => 'Archived',          'badge' => $notificationsArchivedCount],
];

$notificationsPerPage = 8;
$notificationsPagination = hc_paginate($notifications, 'page', $notificationsPerPage);
$notifications = $notificationsPagination['items'];
$notificationsTotal = $notificationsPagination['total'];
$notificationsTotalPages = $notificationsPagination['totalPages'];
$notificationsCurrentPage = $notificationsPagination['page'];
