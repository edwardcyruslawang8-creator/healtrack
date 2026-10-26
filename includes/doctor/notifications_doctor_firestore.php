<?php
/**
 * =====================================================================
 * Notifications (Doctor) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — every row is a real notification generated live from two
 * sources scoped to this signed-in doctor's own caseload:
 * includes/firestore/consultations.php's hc_get_followup_notifications()
 * (assignedPersonnel matches their real name — one entry per
 * consultation with a real "nextFollowUp" date due within the next 7
 * days or already overdue) and includes/firestore/lab_results.php's
 * hc_get_doctor_result_ready_notifications() (orderedBy matches their
 * real name — one entry per lab/x-ray result a medical technologist
 * has completed within the last 14 days, so a doctor is actually told
 * when a result they ordered becomes available instead of only finding
 * out by reopening the consultation). This replaces the previous
 * fully-fabricated 8-row placeholder list (patient registrations,
 * stock requests, etc. — none of which reflect anything real for this
 * role) with the notification-worthy real data sources that actually
 * exist for a doctor right now.
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
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_doctor_result_ready_notifications()
require_once __DIR__ . '/../shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
require_once __DIR__ . '/../shared/phone_reminder.php'; // hc_get_phone_reminder_notification()
require_once __DIR__ . '/../shared/email_verification_reminder.php'; // hc_my_email_verification_notification()
require_once __DIR__ . '/../shared/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$doctorProfile = [
    'name'     => $_SESSION['name'] ?? 'Doctor',
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

$notificationTypeOptions = ['All Types', 'Consultation', 'Reminder'];

// ---------------------------------------------------------------------
// Notifications List — real, merging live sources scoped to this
// doctor's own caseload. Archived ones (api/archive-notifications.php)
// STAY in this list, tagged 'archived' => true, rather than being
// excluded — the Archived tab (assets/js/notifications.js) needs them
// to actually show something; every other tab hides an archived row
// client-side instead. 'unread' wins from real session-persisted read
// state over the default "everything starts unread" assumption for a
// notification not yet marked read.
// ---------------------------------------------------------------------
$ntfReadIds = $_SESSION['ntfReadIds'] ?? [];
$ntfArchivedIds = $_SESSION['ntfDismissedIds'] ?? [];
$rawNotifications = hc_notification_preference_enabled_for_doc($myDoc ?? [], 'system') ? array_merge(
    array_filter([hc_get_2fa_reminder_notification($myUid, $myDoc ?? [])]),
    array_filter([hc_get_phone_reminder_notification($myUid, $myDoc ?? [])]),
    array_filter([hc_my_email_verification_notification()]),
    hc_notification_preference_enabled_for_doc($myDoc ?? [], 'consultation') ? hc_get_followup_notifications($doctorProfile['name']) : [],
    hc_get_doctor_result_ready_notifications($doctorProfile['name'])
) : [];
// Which real page a notification's "Go to details" button (the
// Notification Details modal's own primary action, assets/js/notifications.js)
// should open — keyed by the same real 'type' string each source
// above already sets, not by notification id. A type with no entry
// here just gets no button at all, not a wrong link.
$notificationTypeLinks = [
    'Consultation' => ['url' => 'consultations.php', 'label' => 'Go to Consultations'],
    'Laboratory' => ['url' => 'patient_laboratory_xray.php', 'label' => 'Go to Lab & X-ray'],
    'X-ray' => ['url' => 'patient_laboratory_xray.php', 'label' => 'Go to Lab & X-ray'],
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
// Notification tabs — all filter the table client-side (see
// assets/js/notifications.js): "All Notifications"/"Unread" by
// $n['unread'], "Archived" by $n['archived'] — and, the other way
// around, every tab EXCEPT "Archived" hides an archived row, so
// archiving something really does take it out of the main view. Built
// from $notifications (after the archived-tagging above) so the
// Archived badge counts the real total, not just one page's rows.
// "All Notifications" has no badge at all — a badge there went through
// two designs (mirroring the unread count, then a real total count)
// before landing on no number at all being the clearest option.
// ---------------------------------------------------------------------
$notificationsUnreadCount = hc_doctor_unread_notification_count();
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
