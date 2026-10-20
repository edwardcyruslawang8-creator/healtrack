<?php
/**
 * =====================================================================
 * Notifications — Placeholder Data
 * ---------------------------------------------------------------------
 * PARTIALLY connected — this is a top-level Sub-Admin section (its own
 * sidebar nav item), so it derives $adminProfile/$facilityOptions the
 * same way includes/user_management_firestore.php and its siblings do.
 * Most of the list below (7 rows) is still static placeholder data —
 * there's no general-purpose notifications collection anywhere in this
 * app yet (every real notification across every role is computed live
 * from its own real data source instead, same as the rest of this
 * app's notification pages). Three sources here ARE real: new-account
 * requests awaiting approval (includes/firestore/accounts.php's
 * hc_get_pending_account_notifications() — a sub-admin is notified the
 * moment someone registers via register.php), the medicine/vaccine
 * expiry alerts (includes/firestore/medicines.php's
 * hc_get_medicine_expiry_notifications(), replacing what used to be a
 * single fake "Expired Medicine Alert" seed row), and the 2FA reminder
 * below. $adminProfile is real too — read from the signed-in sub-
 * admin's own Firestore users/{uid} document.
 *
 * Pagination is real over whatever this ends up being (placeholder rows
 * + however many real expiry alerts exist right now) — it used to be
 * hardcoded to a fake 6 pages purely to match a reference design's
 * mockup, but a control implying pages of notifications that don't
 * exist is itself a dead button, so it's honest now instead.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_medicine_expiry_notifications()
require_once __DIR__ . '/../firestore/accounts.php'; // hc_get_pending_account_notifications()
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
// Placeholder rows — Future: SELECT ... FROM notifications. 'unread' is
// an arbitrary flag on the first 5 of these 7 rows so the "Unread" tab
// filter and the badge counters have something to demonstrate against
// even before any real expiry alert exists.
// ---------------------------------------------------------------------
$notifications = [
    ['id' => 1, 'title' => 'New Patient Registered',   'description' => 'Juan Dela Cruz has been registered as a new patient.',                 'type' => 'Patient',           'date' => 'May 20, 2026', 'time' => '09:15 AM', 'icon' => 'bi-person-plus-fill',       'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'unread' => true],
    ['id' => 2, 'title' => 'Low Stock Alert',           'description' => 'Paracetamol 500mg Tablet is running low in stock.',                     'type' => 'Inventory', 'date' => 'May 20, 2026', 'time' => '08:45 AM', 'icon' => 'bi-capsule',                'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'unread' => true],
    ['id' => 3, 'title' => 'Consultation Log Updated',  'description' => 'A consultation log was updated by Ralph Mendoza.',                      'type' => 'System',             'date' => 'May 20, 2026', 'time' => '08:30 AM', 'icon' => 'bi-clipboard2-pulse-fill',  'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'unread' => true],
    ['id' => 4, 'title' => 'Appointment Reminder',      'description' => 'You have 3 scheduled appointments today.',                              'type' => 'Reminder',      'date' => 'May 20, 2026', 'time' => '07:00 AM', 'icon' => 'bi-calendar-check-fill',    'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'unread' => true],
    ['id' => 5, 'title' => 'System Update',             'description' => 'System maintenance will be on May 22, 2026 from 10:00 PM to 2:00 AM.',  'type' => 'System',       'date' => 'May 19, 2026', 'time' => '06:15 PM', 'icon' => 'bi-shield-fill-check',      'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'unread' => true],
    ['id' => 6, 'title' => 'Stock Request Approved',    'description' => 'Your stock request REQ-2026-00025 has been approved.',                 'type' => 'Stock',           'date' => 'May 19, 2026', 'time' => '04:25 PM', 'icon' => 'bi-cart-fill',              'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'unread' => false],
    ['id' => 7, 'title' => 'New User Added',            'description' => 'Liza Reyes has been added as a new Pharmacist.',                        'type' => 'User',               'date' => 'May 19, 2026', 'time' => '03:10 PM', 'icon' => 'bi-people-fill',            'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'unread' => false],
];

// Real medicine/vaccine expiry alerts (includes/firestore/medicines.php's
// hc_get_medicine_expiry_notifications()) — replaces what used to be
// seed row id 8, a single hardcoded "Expired Medicine Alert" fake row
// that never reflected real inventory. Prepended like the 2FA reminder
// below, not appended, for the same reason: the 7 seed rows above
// already fill page 1 on their own. Gated by 'inventory' — see
// includes/shared/notification_preferences.php's header comment.
$expiryNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'inventory')
    ? hc_get_medicine_expiry_notifications()
    : [];
foreach ($expiryNotifications as &$expiryNotification) {
    $expiryNotification['unread'] = true;
}
unset($expiryNotification);

// Real new-account requests awaiting this sub-admin's approval
// (includes/firestore/accounts.php's hc_get_pending_account_notifications(),
// register.php/api/register.php) — same facility-scoped isolation
// subadmin/user_management.php's "Pending Approvals" table already
// applies. Doesn't fit 'inventory'/'consultation'/'consent' any more
// cleanly than doctor's patient-access-request notifications or
// medical_technician's pending lab/x-ray ones do, so it's gated by
// 'system' only too — see notification_preferences.php's header comment.
$pendingAccountNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? hc_get_pending_account_notifications()
    : [];
foreach ($pendingAccountNotifications as &$pendingAccountNotification) {
    $pendingAccountNotification['unread'] = true;
}
unset($pendingAccountNotification);

$notifications = array_merge($pendingAccountNotifications, $expiryNotifications, $notifications);

// The real 2FA reminder (see config/constants.php's
// subadmin_unread_notification_count(), which already counts this toward
// the sidebar/bell/tab badges) needs a real row here too — otherwise it's
// counted but never shown, so a sub-admin without 2FA enabled can never
// get the badge below 1 no matter what they do on this page. Prepended
// ahead of everything else (even the real expiry alerts above), not
// appended: the placeholder rows already fill page 1 on their own, so
// appending would silently bury this behind pagination instead of
// actually being visible/actionable.
// Gated by 'system' (master "Receive in-app notifications" toggle) —
// see includes/shared/notification_preferences.php's header comment.
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
// so it reflects the real total, not just this page's first page.
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
