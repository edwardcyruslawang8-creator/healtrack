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
 * app's notification pages). Four sources here ARE real: new-account
 * requests awaiting approval (includes/firestore/accounts.php's
 * hc_get_pending_account_notifications() — a sub-admin is notified the
 * moment someone registers via register.php), new stock requests
 * awaiting a decision (includes/firestore/stock_requests.php's
 * hc_get_pending_stock_request_notifications() — a sub-admin is
 * notified the moment a pharmacist submits one via
 * api/create-stock-request.php), the medicine/vaccine expiry alerts
 * (includes/firestore/medicines.php's hc_get_medicine_expiry_notifications(),
 * replacing what used to be a single fake "Expired Medicine Alert"
 * seed row), and the 2FA reminder below. $adminProfile is real too —
 * read from the signed-in sub-admin's own Firestore users/{uid}
 * document.
 *
 * Pagination is real over whatever this ends up being (placeholder rows
 * + however many real expiry alerts exist right now) — it used to be
 * hardcoded to a fake 6 pages purely to match a reference design's
 * mockup, but a control implying pages of notifications that don't
 * exist is itself a dead button, so it's honest now instead. It's also
 * entirely client-side (assets/js/notifications.js) — this file hands
 * the page every notification unsliced, since pagination needs to
 * happen AFTER the (also client-side) tab/type/search filtering, not
 * before it; slicing here first used to mean a given page's 8 rows
 * didn't reliably match whatever tab was actually active.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_medicine_expiry_notifications()
require_once __DIR__ . '/../firestore/accounts.php'; // hc_get_pending_account_notifications()
require_once __DIR__ . '/../firestore/stock_requests.php'; // hc_get_pending_stock_request_notifications()
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

// Real pending stock requests awaiting this sub-admin's decision
// (includes/firestore/stock_requests.php's
// hc_get_pending_stock_request_notifications(), api/create-stock-request.php)
// — same facility-scoped isolation and 'system' gating as the pending
// new-account requests above (doesn't fit a more specific preference
// category any more cleanly than those do).
$pendingStockRequestNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? hc_get_pending_stock_request_notifications()
    : [];
foreach ($pendingStockRequestNotifications as &$pendingStockRequestNotification) {
    $pendingStockRequestNotification['unread'] = true;
}
unset($pendingStockRequestNotification);

// Real confirmation of a stock request THIS sub-admin personally
// decided (approved/rejected/released) — see that function's own
// comment (includes/firestore/stock_requests.php) for why it's
// time-windowed and scoped to decisions THIS sub-admin made, not
// anyone else at the facility. Otherwise a decided request just
// disappears from $pendingStockRequestNotifications above the moment
// it's acted on, with no trace left behind.
$decidedStockRequestNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? hc_get_decided_stock_request_notifications_for_subadmin()
    : [];
foreach ($decidedStockRequestNotifications as &$decidedStockRequestNotification) {
    $decidedStockRequestNotification['unread'] = true;
}
unset($decidedStockRequestNotification);

$notifications = array_merge($pendingAccountNotifications, $pendingStockRequestNotifications, $decidedStockRequestNotifications, $expiryNotifications, $notifications);

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
// Which real page a notification's "Go to details" button (the
// Notification Details modal's own primary action, assets/js/notifications.js)
// should open — keyed by the same real 'type' string each source
// above already sets, not by notification id (several ids can share a
// type and the same real destination). A type with no entry here (the
// fake placeholder rows' 'Patient'/'System'/'Stock' types) just gets
// no button at all, not a wrong link.
$notificationTypeLinks = [
    'User' => ['url' => 'user_management.php', 'label' => 'Go to User Management'],
    'Stock Request' => ['url' => 'stock_requests.php', 'label' => 'Go to Stock Requests'],
    'Inventory' => ['url' => 'medicine_vaccine_inventory.php', 'label' => 'Go to Inventory'],
    'Reminder' => ['url' => 'profile_settings.php', 'label' => 'Go to Security Settings'],
];

$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
foreach ($notifications as &$n) {
    if (in_array($n['id'], $ntfReadIds, true)) {
        $n['unread'] = false;
    }
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);
    $n['link'] = $notificationTypeLinks[$n['type']]['url'] ?? null;
    $n['linkLabel'] = $notificationTypeLinks[$n['type']]['label'] ?? null;
}
unset($n);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side now (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived']. "All Notifications" has
// no badge at all — a badge there went through two designs (first
// mirroring the unread count, which looked like a bug since "All" and
// "Unread" showed the same number; then a real total count, which
// looked confusing in a different way since it didn't move when
// something was marked read) before landing on no number at all being
// the clearest option. "Unread"/"Archived" keep real badges — the
// "unread" one uses the same session-backed count as every page's
// sidebar/topbar badges (config/constants.php) rather than a local
// recount, so they agree with each other on first paint.
// ---------------------------------------------------------------------
$notificationsUnreadCount = subadmin_unread_notification_count();
$notificationsArchivedCount = count(array_filter($notifications, fn ($n) => $n['archived']));

$notificationTabs = [
    ['key' => 'all',            'label' => 'All Notifications', 'active' => true],
    ['key' => 'unread',         'label' => 'Unread',            'badge' => $notificationsUnreadCount],
    ['key' => 'archived',       'label' => 'Archived',          'badge' => $notificationsArchivedCount],
];

// Pagination is client-side now (assets/js/notifications.js) — every
// notification renders into the table below, unsliced, and the browser
// shows/hides 8 at a time depending on which tab/filter/page is active.
// See that file's header comment for why this moved off the server:
// slicing here, before the tab filter even applies client-side, meant
// a page's 8 rows didn't reliably line up with what any given tab was
// actually showing. $notificationsTotal/$notificationsPerPage below are
// just the initial "Showing..." text's starting point — JS recomputes
// the real figure (tab-filtered) the instant it runs.
$notificationsPerPage = 8;
$notificationsTotal = count($notifications);
