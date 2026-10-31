<?php
/**
 * =====================================================================
 * Sub-Administrator Management — Data
 * ---------------------------------------------------------------------
 * $subadmins is now read live from Firestore's "users" collection
 * (role === 'subadmin'), populated whenever an admin submits the "Add
 * Sub-Administrator" modal (includes/add_subadmin_modal.php ->
 * api/create-subadmin.php, which creates the Firebase Auth account and
 * this Firestore profile document together).
 *
 * $adminProfile and $facilities come from facility_management_firestore.php
 * (identical $adminProfile logic to every other admin page, plus the
 * facility list the "Add Sub-Administrator" modal's dropdown needs)
 * rather than duplicating both here.
 * =====================================================================
 */

require_once __DIR__ . '/facility_management_firestore.php'; // $adminProfile, $facilities
require_once __DIR__ . '/../../config/firebase.php';        // $firestore, $firebaseAuth
require_once __DIR__ . '/../shared/avatar_helpers.php';            // derive_initials(), derive_avatar_color()
require_once __DIR__ . '/../shared/fuzzy_search.php';               // hc_fuzzy_match()

// ---------------------------------------------------------------------
// Status filter tabs — real now, a ?status= query param narrowing the
// table to that one real status (same $s['status'] field the table's
// own badges and the summary stat cards above already read), carried
// through search/pagination by hc_sam_query() below same as ?q=/?page=.
// ---------------------------------------------------------------------
$subadminStatusTabKeys = ['all', 'active', 'inactive', 'locked'];
$subadminStatusFilter = $_GET['status'] ?? 'all';
if (!in_array($subadminStatusFilter, $subadminStatusTabKeys, true)) {
    $subadminStatusFilter = 'all';
}
$subadminTabs = [
    ['key' => 'all',      'label' => 'All',      'active' => $subadminStatusFilter === 'all'],
    ['key' => 'active',   'label' => 'Active',   'active' => $subadminStatusFilter === 'active'],
    ['key' => 'inactive', 'label' => 'Inactive', 'active' => $subadminStatusFilter === 'inactive'],
    ['key' => 'locked',   'label' => 'Locked',   'active' => $subadminStatusFilter === 'locked'],
];

// Role → badge color lookup, so the table loop doesn't hardcode colors per row.
$roleColors = [
    'Facility Admin'    => ['bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'Records Manager'   => ['bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'Nurse Manager'     => ['bg' => '#FDECDD', 'color' => '#E8710A'],
    'Inventory Manager' => ['bg' => '#E4F7FA', 'color' => '#0E9CA8'],
    'Clerk'             => ['bg' => '#FCE4E4', 'color' => '#D93025'],
];

// Status → legend description, reused by both the table badges and the
// legend strip at the bottom so the two can't drift out of sync.
$statusLegend = [
    'active'   => ['label' => 'Active',   'desc' => 'Account is active and accessible'],
    'inactive' => ['label' => 'Inactive', 'desc' => 'No login for more than 30 days'],
    'locked'   => ['label' => 'Locked',   'desc' => 'Account locked due to failed login attempts'],
    'pending'  => ['label' => 'Pending',  'desc' => 'Invitation not yet accepted'],
];

// Option lists for the "Edit Sub-Administrator" modal's Role/Status selects.
$roleOptions = array_keys($roleColors);
$statusOptions = array_keys($statusLegend);

// The legend strip at the bottom of the page shows Active/Inactive/Locked
// only — Pending stays in $statusLegend itself so a pending account still
// gets a labelled badge and the Edit modal keeps its status option.
$statusLegendStrip = array_diff_key($statusLegend, ['pending' => true]);

// ---------------------------------------------------------------------
// Real sub-administrator accounts. Firestore's REST API (via
// firebase/FirestoreRestClient.php) doesn't support server-side field
// filters here, so this lists every "users" doc and filters to
// role === 'subadmin' in PHP — fine at this app's scale.
// ---------------------------------------------------------------------
$subadminDocs = array_values(array_filter(
    $firestore->listDocuments('users'),
    fn ($doc) => ($doc['fields']['role'] ?? null) === 'subadmin'
));

// Last Login comes from Firebase Auth (UserMetaData::$lastLoginAt), which
// Firebase updates itself on every successful sign-in (login.js's
// signInWithEmailAndPassword()/signInWithPopup()) — same real source
// includes/subadmin/user_management_firestore.php already reads. One
// batched getUsers() call instead of a getUser() per row; if that lookup
// fails, rows fall back to "Unknown" rather than breaking the page.
$subadminAuthUsers = [];
$subadminAuthLookupFailed = false;
if ($subadminDocs !== []) {
    try {
        $subadminAuthUsers = $firebaseAuth->getUsers(array_column($subadminDocs, 'id'));
    } catch (\Throwable $e) {
        $subadminAuthLookupFailed = true;
    }
}

$subadmins = [];
foreach ($subadminDocs as $doc) {
    $fields = $doc['fields'];

    $lastLoginLabel = 'Never logged in';
    $authUser = $subadminAuthUsers[$doc['id']] ?? null;
    if ($subadminAuthLookupFailed) {
        $lastLoginLabel = 'Unknown';
    } elseif ($authUser !== null && $authUser->metadata->lastLoginAt !== null) {
        $lastLoginLabel = $authUser->metadata->lastLoginAt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y g:i A');
    }

    $name = $fields['name'] ?? $fields['email'] ?? 'Unknown';
    $subadmins[] = [
        'id' => $doc['id'],
        'initials' => derive_initials($name),
        'avatarColor' => derive_avatar_color($doc['id']),
        'name' => $name,
        'email' => $fields['email'] ?? '',
        'facility' => $fields['facility'] ?? 'Unassigned',
        'facilityId' => $fields['facilityId'] ?? '',
        'location' => $fields['facilityLocation'] ?? '',
        'role' => $fields['subadminRole'] ?? 'Facility Admin',
        'status' => $fields['status'] ?? 'active',
        'lastLogin' => $lastLoginLabel,
    ];
}

// ---------------------------------------------------------------------
// Summary stat cards — derived from the FULL real $subadmins list above
// (before the search box narrows anything below) so "All time"/overall
// counts stay accurate regardless of what's currently searched for,
// same convention every other role's summary stat cards already follow.
// ---------------------------------------------------------------------
$statusCounts = ['active' => 0, 'inactive' => 0, 'locked' => 0, 'pending' => 0];
foreach ($subadmins as $s) {
    if (isset($statusCounts[$s['status']])) {
        $statusCounts[$s['status']]++;
    }
}

$subadminStats = [
    ['icon' => 'bi-people-fill',       'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Sub-Administrators', 'value' => count($subadmins),         'sub' => 'All time'],
    ['icon' => 'bi-person-check-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Active Accounts',          'value' => $statusCounts['active'],   'sub' => 'Currently active'],
    ['icon' => 'bi-person-dash-fill',  'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Inactive Accounts',        'value' => $statusCounts['inactive'], 'sub' => 'Currently inactive'],
    ['icon' => 'bi-lock-fill',         'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Locked Accounts',          'value' => $statusCounts['locked'],   'sub' => 'Currently locked'],
];

// ---------------------------------------------------------------------
// Search box (?q=) — real fuzzy matching now (hc_fuzzy_match(), same
// Levenshtein-based algorithm every other real search box in this app
// uses), over name/email/facility, matching the placeholder text's own
// promise ("Search by name, email or facility..."). Narrows the TABLE
// only — the stat cards above stay computed from the full list.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$filteredSubadmins = $searchTerm === '' ? $subadmins : array_values(array_filter(
    $subadmins,
    fn ($s) => hc_fuzzy_match($s['name'] . ' ' . $s['email'] . ' ' . $s['facility'], $searchTerm)
));

// Status tab filter — applied on top of the search above, same real
// $s['status'] field the table badges/legend/summary cards all share.
if ($subadminStatusFilter !== 'all') {
    $filteredSubadmins = array_values(array_filter(
        $filteredSubadmins,
        fn ($s) => $s['status'] === $subadminStatusFilter
    ));
}

$subadminsTotal = count($filteredSubadmins);
$subadminsPerPage = 8;
// Was hardcoded to 1 with $filteredSubadmins never actually sliced
// below — every row past the first 8 matches was still rendered
// regardless of "page," and the Previous/page-number/Next links in
// admin/subadmin_management.php were real dead links (href="#")
// pointing at nothing. Real now, same pattern as every other paginated
// admin list this session.
$subadminsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$subadminsTotalPages = (int) max(1, ceil($subadminsTotal / $subadminsPerPage));
$subadminsCurrentPage = min($subadminsCurrentPage, $subadminsTotalPages);

$filteredSubadmins = array_slice($filteredSubadmins, ($subadminsCurrentPage - 1) * $subadminsPerPage, $subadminsPerPage);

if (!function_exists('hc_sam_query')) {
    /** Builds a ?query string carrying the current ?q= search and ?status= tab except whatever's in $overrides — used by the pagination/tab links so paging/switching tabs never drops the other active filter. */
    function hc_sam_query(array $overrides = []): string
    {
        $params = array_merge([
            'q' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null));
    }
}
