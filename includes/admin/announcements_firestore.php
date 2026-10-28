<?php
/**
 * =====================================================================
 * Announcements (Admin) — Data
 * ---------------------------------------------------------------------
 * Two real, separate things on this page:
 *
 * 1. Pending Approval queue — every "announcements" doc a sub-admin has
 *    submitted (api/create-announcement.php) that's still
 *    status='pending', reading the exact same real Firestore collection
 *    patient/announcements.php and index.php's widget do (via
 *    firebase/firestore.php's getAllAnnouncements(), which — unlike
 *    those two callers — is NOT filtered to 'approved' here, since an
 *    admin needs to see every status to review it).
 *    api/approve-announcement.php / api/reject-announcement.php flip
 *    status to 'approved'/'rejected'; approving is the one thing that
 *    actually makes a sub-admin's post visible to patients.
 *
 * 2. Send a Note to Sub-Admins — a private, internal admin -> sub-admin
 *    message, entirely separate from the patient-facing "announcements"
 *    collection (see api/create-admin-note.php's own header comment for
 *    why). Every note ever sent is listed below the composer, newest
 *    first.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../../firebase/firestore.php'; // getAllAnnouncements()

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        default => 'Administrator',
    },
];

// Real facilities that actually have a sub-administrator to receive a
// note — not the old hardcoded 3-name list (which still offered East/
// West Super Health Center as choices even with zero real
// sub-administrators assigned there, a note nobody could ever see).
// Derived from the real "users" collection rather than the "facilities"
// collection itself, since what matters here is "is there someone to
// actually send this to", not just "does this facility exist".
$facilitiesWithSubadmins = [];
foreach ($firestore->listDocuments('users') as $doc) {
    $f = $doc['fields'];
    if (($f['role'] ?? '') === 'subadmin' && !empty($f['facility'])) {
        $facilitiesWithSubadmins[$f['facility']] = true;
    }
}
$facilityOptions = array_keys($facilitiesWithSubadmins);
sort($facilityOptions);
$noteFacilityOptions = array_merge(['All Facilities'], $facilityOptions);

$categoryMeta = [
    'general' => ['label' => 'General', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'alert'   => ['label' => 'Alert',   'bg' => '#FCE4E4', 'color' => '#D93025'],
    'event'   => ['label' => 'Event',   'bg' => '#E8F0FE', 'color' => '#1A73E8'],
];

// ---------------------------------------------------------------------
// Pending Approval queue — real, unfiltered by status (this is the one
// place in the app that needs every status, not just 'approved').
// ---------------------------------------------------------------------
$allAnnouncements = getAllAnnouncements();
$pendingAnnouncements = array_values(array_filter($allAnnouncements, fn ($a) => $a['status'] === 'pending'));

$announcementSummary = [
    'pending'  => count($pendingAnnouncements),
    'approved' => count(array_filter($allAnnouncements, fn ($a) => $a['status'] === 'approved')),
    'rejected' => count(array_filter($allAnnouncements, fn ($a) => $a['status'] === 'rejected')),
];

// ---------------------------------------------------------------------
// Notes sent to sub-admins — real, from the separate "admin_notes"
// collection (see api/create-admin-note.php's own header comment).
// ---------------------------------------------------------------------
$allAdminNotes = [];
foreach ($firestore->listDocuments('admin_notes') as $doc) {
    $fields = $doc['fields'];
    $allAdminNotes[] = [
        'id' => $doc['id'],
        'title' => $fields['title'] ?? '',
        'message' => $fields['message'] ?? '',
        'facility' => $fields['facility'] ?? 'All Facilities',
        'postedBy' => $fields['postedBy'] ?? 'System Administrator',
        'postedAt' => $fields['postedAt'] ?? '',
        'date' => isset($fields['postedAt']) && $fields['postedAt'] !== ''
            ? date('M j, Y g:i A', strtotime($fields['postedAt']))
            : '',
    ];
}
usort($allAdminNotes, fn ($a, $b) => strcmp($b['postedAt'], $a['postedAt']));
