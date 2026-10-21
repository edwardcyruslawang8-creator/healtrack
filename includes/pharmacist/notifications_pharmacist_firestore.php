<?php
/**
 * =====================================================================
 * Notifications (Pharmacist) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every row is a real notification generated live from
 * THREE merged sources: includes/firestore/medicines.php's
 * hc_get_low_stock_notifications() (one entry per medicine/vaccine
 * currently at "low" or "unavailable" stock status, across both the
 * Medicines and Vaccines inventory), that same file's
 * hc_get_medicine_expiry_notifications() (one entry per medicine/
 * vaccine already past its real expiryDate or expiring within 30 days),
 * and includes/firestore/pharmacy_orders.php's
 * hc_get_pending_pharmacy_order_notifications() (one entry per real
 * medicine/vaccine a doctor prescribed — api/complete-consultation.php's
 * "pharmacy" routing block — still awaiting a "Dispense" on
 * pharmacist/prescription_queue.php). This replaces the previous fully-
 * fabricated 8-row placeholder list (patient registrations,
 * consultation logs, etc. — none of which reflect anything real for
 * this role) with the notification-worthy real data sources that
 * actually exist for a pharmacist: their own inventory's stock levels,
 * and their own dispensing queue.
 *
 * Which of them are READ is real and persisted server-side (same
 * mechanism as subadmin/notifications.php —
 * api/mark-notifications-read.php -> $_SESSION['ntfReadIds'], which is
 * what config/constants.php's hc_pharmacist_unread_notification_count()
 * — this page's tab badges, the sidebar nav badge, and the topbar bell
 * badge, on THIS page and every other pharmacist/*.php page — counts
 * against instead of a fixed seed list, since the universe of real
 * notification ids here changes as stock levels change).
 *
 * There's no Delete action any more — pharmacist/notifications.php's
 * Actions column is a "View Details" button plus a real Archive/
 * Unarchive action (api/archive-notifications.php /
 * api/unarchive-notifications.php), using that exact same
 * $_SESSION['ntfDismissedIds'] mechanism, now repurposed: an archived
 * notification STAYS in $notifications below (tagged 'archived' =>
 * true) instead of being excluded, so the Archived tab has something
 * to show; every other tab hides it client-side instead.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_low_stock_notifications(), hc_get_medicine_expiry_notifications()
require_once __DIR__ . '/../firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_notifications()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'Inventory', 'Medicine', 'Vaccine', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, one row per low/out-of-stock inventory
// item OR still-pending prescription (both sources merged, oldest
// prescriptions/most urgent stock first). Archived ones STAY in this
// list, tagged 'archived' => true (see header comment). 'unread' wins
// from real session-persisted read state over the default "everything
// starts unread" assumption for a notification not yet marked read.
// ---------------------------------------------------------------------
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
// Real gating — see includes/shared/notification_preferences.php's
// header comment for which key covers which real source here.
$rawNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system') ? array_merge(
    array_filter([hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])]),
    array_filter([hc_get_phone_reminder_notification($myUid, $myDoc ?? [])]),
    hc_notification_preference_enabled_for_doc($myDoc ?? [], 'inventory') ? hc_get_low_stock_notifications() : [],
    hc_notification_preference_enabled_for_doc($myDoc ?? [], 'inventory') ? hc_get_medicine_expiry_notifications() : [],
    hc_notification_preference_enabled_for_doc($myDoc ?? [], 'inventory') ? hc_get_pending_pharmacy_order_notifications() : []
) : [];
$notifications = array_map(function ($n) use ($ntfReadIds, $ntfArchivedIds) {
    $n['unread'] = !in_array($n['id'], $ntfReadIds, true);
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);

    return $n;
}, $rawNotifications);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived']. Built from $notifications
// (after the archived-tagging above, before pagination below) so the
// Archived badge counts the real total, not just this page's 8 rows.
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_pharmacist_unread_notification_count();
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
