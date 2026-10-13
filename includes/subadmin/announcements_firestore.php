<?php
/**
 * =====================================================================
 * Announcements (Sub-Admin) — Data
 * ---------------------------------------------------------------------
 * New top-level Sub-Admin section — lets a sub-admin post real
 * announcements that immediately appear on the public-facing
 * patient/announcements.php page (and index.php's "latest 3" widget),
 * both of which already read the same real "announcements" Firestore
 * collection via firebase/firestore.php's getAllAnnouncements(). No
 * changes were needed on the patient side — posting here is enough.
 *
 * This page itself is read (list existing announcements, for context)
 * + create (the "+ New Announcement" modal -> api/create-announcement.php)
 * only — there's deliberately no Edit/Delete action yet, same
 * "connects the write side this pass needed, not full lifecycle
 * management" scope line drawn elsewhere this session.
 *
 * NEW post now needs a System Administrator's approval before it's
 * public (api/create-announcement.php always stamps status='pending')
 * — $allAnnouncements here is read UNFILTERED by status (unlike
 * includes/public/announcements-data.php's own copy, which only ever
 * shows 'approved'), since a sub-admin needs to see their own
 * Pending/Approved/Rejected posts, not just the live ones.
 *
 * $adminNotes is a second, separate real read — the "admin_notes"
 * collection admin/announcements.php's "Send a Note to Sub-Admins"
 * writes to (api/create-admin-note.php) — filtered to notes meant for
 * this sub-admin: either 'All Facilities' or their own real facility.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../../firebase/firestore.php'; // getAllAnnouncements()
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

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

// Same category labels/colors as includes/public/announcements-data.php's
// $categoryMeta, so the badge here matches what patients actually see.
$categoryMeta = [
    'general' => ['label' => 'General', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'alert'   => ['label' => 'Alert',   'bg' => '#FCE4E4', 'color' => '#D93025'],
    'event'   => ['label' => 'Event',   'bg' => '#E8F0FE', 'color' => '#1A73E8'],
];
$categoryOptions = ['All Categories', 'General', 'Alert', 'Event'];

// ---------------------------------------------------------------------
// Real announcement list — same "announcements" collection the public
// patient/announcements.php page reads, newest first (already sorted
// that way by getAllAnnouncements()).
// ---------------------------------------------------------------------
$allAnnouncements = getAllAnnouncements();

$announcementSummary = [
    'total' => count($allAnnouncements),
    'important' => count(array_filter($allAnnouncements, fn ($a) => $a['important'])),
    'pending' => count(array_filter($allAnnouncements, fn ($a) => $a['status'] === 'pending')),
];

// ---------------------------------------------------------------------
// Apply the search box (?q=) and category filter (?category=) for real.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$categoryFilter = $_GET['category'] ?? 'All Categories';
$categoryLabelToKey = ['General' => 'general', 'Alert' => 'alert', 'Event' => 'event'];

$filteredAnnouncements = array_values(array_filter($allAnnouncements, function ($a) use ($searchTerm, $categoryFilter, $categoryLabelToKey) {
    if ($searchTerm !== '' && !hc_fuzzy_match($a['title'] . ' ' . $a['desc'], $searchTerm)) {
        return false;
    }
    if ($categoryFilter !== 'All Categories' && $a['category'] !== ($categoryLabelToKey[$categoryFilter] ?? '')) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$announcementsPerPage = 8;
$announcementsPagination = hc_paginate($filteredAnnouncements, 'page', $announcementsPerPage);
$announcements = $announcementsPagination['items'];
$announcementsTotal = $announcementsPagination['total'];
$announcementsTotalPages = $announcementsPagination['totalPages'];
$announcementsCurrentPage = $announcementsPagination['page'];

// ---------------------------------------------------------------------
// Notes from Admin — real "admin_notes" docs targeted at this sub-admin
// (either 'All Facilities' or their own real facility), newest first.
// ---------------------------------------------------------------------
$adminNotes = [];
foreach ($firestore->listDocuments('admin_notes') as $doc) {
    $fields = $doc['fields'];
    $noteFacility = $fields['facility'] ?? 'All Facilities';
    if ($noteFacility !== 'All Facilities' && $noteFacility !== $adminProfile['facility']) {
        continue;
    }
    $adminNotes[] = [
        'id' => $doc['id'],
        'title' => $fields['title'] ?? '',
        'message' => $fields['message'] ?? '',
        'facility' => $noteFacility,
        'postedBy' => $fields['postedBy'] ?? 'System Administrator',
        'postedAt' => $fields['postedAt'] ?? '',
        'date' => isset($fields['postedAt']) && $fields['postedAt'] !== ''
            ? date('M j, Y g:i A', strtotime($fields['postedAt']))
            : '',
    ];
}
usort($adminNotes, fn ($a, $b) => strcmp($b['postedAt'], $a['postedAt']));
