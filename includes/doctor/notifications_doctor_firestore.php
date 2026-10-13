<?php
/**
 * =====================================================================
 * Notifications (Doctor) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every row is a real notification generated live from
 * includes/firestore/consultations.php's hc_get_followup_notifications(),
 * scoped to this signed-in doctor's own caseload (assignedPersonnel
 * matches their real name): one entry per consultation with a real
 * "nextFollowUp" date due within the next 7 days or already overdue.
 * This replaces the previous fully-fabricated 8-row placeholder list
 * (patient registrations, stock requests, etc. — none of which reflect
 * anything real for this role) with the one notification-worthy real
 * data source that actually exists for a doctor right now: their own
 * patients' upcoming follow-ups. With this app's current seed data
 * (one real consultation, assigned to a different name) this list will
 * likely be empty for most doctor accounts — genuinely, not a bug; see
 * includes/firestore/consultations.php's header comment.
 *
 * Which of them are READ is real and persisted server-side (same
 * mechanism as pharmacist/notifications.php —
 * api/mark-notifications-read.php -> $_SESSION['ntfReadIds']), which is
 * what config/constants.php's hc_doctor_unread_notification_count() —
 * this page's tab badges, the sidebar nav badge, and the topbar bell
 * badge, on THIS page and every other doctor/*.php page — counts
 * against instead of a fixed seed list. There's no Delete action any
 * more — doctor/notifications.php's Actions column is a "View Details"
 * button plus a real Archive/Unarchive action
 * (api/archive-notifications.php / api/unarchive-notifications.php),
 * using that exact same $_SESSION['ntfDismissedIds'] mechanism, now
 * repurposed for archiving (see $notifications below).
 *
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_followup_notifications()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()
require_once __DIR__ . '/../shared/patient_access_requests.php'; // hc_get_access_request_notifications()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'Consultation', 'Patient Access', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, merging two live sources: upcoming/overdue
// follow-ups under this doctor's own care, and this workflow's own
// access-request activity (both directions — see
// includes/shared/patient_access_requests.php's hc_get_access_request_notifications()
// header comment). Archived ones (api/archive-notifications.php) STAY
// in this list, tagged 'archived' => true, rather than being excluded —
// the Archived tab (assets/js/notifications.js) needs them to actually
// show something; every other tab hides an archived row client-side
// instead. 'unread' wins from real session-persisted read state over
// the default "everything starts unread" assumption for a notification
// not yet marked read.
// ---------------------------------------------------------------------
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
$rawNotifications = array_merge(
    array_filter([hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])]),
    hc_get_followup_notifications($doctorProfile['name']),
    hc_get_access_request_notifications($myUid)
);
$notifications = array_map(function ($n) use ($ntfReadIds, $ntfArchivedIds) {
    $n['unread'] = !in_array($n['id'], $ntfReadIds, true);
    $n['archived'] = in_array($n['id'], $ntfArchivedIds, true);

    return $n;
}, $rawNotifications);

// ---------------------------------------------------------------------
// Notification tabs — all filter the table client-side (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived'] — and, the other way
// around, every tab EXCEPT "Archived" hides an archived row, so
// archiving something really does take it out of the main view. Built
// from $notifications (after the archived-tagging above, before
// pagination below) so the Archived badge counts the real total, not
// just this page's 8 rows.
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_doctor_unread_notification_count();
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
