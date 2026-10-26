<?php
/**
 * =====================================================================
 * Notifications (Admin) — Data
 * ---------------------------------------------------------------------
 * New — admin had no notifications page, sidebar badge, or topbar bell
 * at all before this (config/constants.php's hc_admin_unread_notification_count()
 * existed, built "more real sources can be merged in here later", but
 * nothing ever actually called it). This page/data file complete that:
 * same real-notification architecture every other role's own
 * notifications_*_firestore.php already uses (tabs filtered client-side
 * by assets/js/notifications.js, read/archived state persisted via
 * api/mark-notifications-read.php / api/archive-notifications.php /
 * api/unarchive-notifications.php, pagination client-side).
 *
 * Two real sources exist for admin right now: the 2FA setup reminder
 * (includes/shared/two_fa_reminder.php's hc_get_2fa_reminder_notification())
 * and its sibling, the email verification reminder
 * (includes/shared/email_verification_reminder.php's
 * hc_my_email_verification_notification()) — the same two every other
 * role already carries, admin's own account is no different. No fake
 * placeholder rows were added to pad this out — an honestly short list
 * beats fabricated data, and more real sources (e.g. something from
 * admin/account_monitoring.php's locked-account data) can be merged in
 * here later the same way subadmin's stock-request notifications were
 * added this session, without touching this file's own structure.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/email_verification_reminder.php'; // hc_my_email_verification_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => 'System Administrator',
];

$dashboardDateLabel = date('F j, Y');

$notificationTypeOptions = ['All Types', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, same session-persisted read/archived
// override pattern every other role's own file already uses.
// ---------------------------------------------------------------------
$twoFaNotification = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system')
    ? hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])
    : null;
$emailVerificationNotification = hc_my_email_verification_notification();
$rawNotifications = array_values(array_filter([$twoFaNotification, $emailVerificationNotification]));

$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];

// Which real page a notification's "Go to details" button (the
// Notification Details modal's own primary action, assets/js/notifications.js)
// should open — keyed by the same real 'type' string the one source
// above sets.
$notificationTypeLinks = [
    'Reminder' => ['url' => 'profile_settings.php', 'label' => 'Go to Security Settings'],
];

$notifications = array_map(function ($n) use ($ntfReadIds, $ntfArchivedIds, $notificationTypeLinks) {
    $n['unread'] = !in_array($n['id'], $ntfReadIds, true);
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);
    $n['link'] = $notificationTypeLinks[$n['type']]['url'] ?? null;
    $n['linkLabel'] = $notificationTypeLinks[$n['type']]['label'] ?? null;

    return $n;
}, $rawNotifications);

// ---------------------------------------------------------------------
// Notification tabs — same client-side filtering/no-"All"-badge design
// every other role's own notifications_*_firestore.php already settled
// on (see any of theirs for the full "why" — two earlier designs for
// the "All" badge both read as confusing before landing on no number
// at all).
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_admin_unread_notification_count();
$notificationsArchivedCount = count(array_filter($notifications, fn ($n) => $n['archived']));

$notificationTabs = [
    ['key' => 'all',            'label' => 'All Notifications', 'active' => true],
    ['key' => 'unread',         'label' => 'Unread',            'badge' => $notificationsUnreadCount],
    ['key' => 'archived',       'label' => 'Archived',          'badge' => $notificationsArchivedCount],
];

// Pagination is client-side (assets/js/notifications.js) — every
// notification renders into the table below, unsliced; see any other
// role's own notifications_*_firestore.php for the full "why".
$notificationsPerPage = 8;
$notificationsTotal = count($notifications);
