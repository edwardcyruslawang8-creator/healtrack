<?php
/**
 * =====================================================================
 * User Management — Data
 * ---------------------------------------------------------------------
 * Reads live from the real "users" Firestore collection — the same
 * users/{uid} documents every role's login/profile-settings flow
 * already reads/writes (see includes/subadmin_profile_settings_firestore.php
 * and its siblings). This is the first page in subadmin/ to list that
 * collection in full rather than a single signed-in user's own doc.
 *
 * Role labels/options are trimmed to the roles that actually exist in
 * this app (admin, subadmin, healthcare_staff, pharmacist,
 * medical_technician, doctor) — an earlier placeholder version had
 * invented roles ("Nurse", "Inventory Staff", "Receptionist") that
 * didn't correspond to any real Firestore role value or login flow,
 * so those stayed gone. "Doctor" WAS one of those placeholder-only
 * entries too, folded into "healthcare_staff" — it's back here as its
 * own real role now that doctor/dashboard.php and its own "doctor"
 * Firestore role value/login flow exist (see
 * includes/doctor/doctor_dashboard_firestore.php's header comment).
 * "patient" is excluded too: this page is a staff directory, and the
 * patient portal has no login flow yet anyway (see login.js's
 * routeByRole()). "admin" (System Administrator) is excluded as
 * well — a sub-admin shouldn't see or manage Administrator accounts
 * at all here, not even read-only; that oversight belongs to
 * admin/subadmin_management.php instead — the row never appears here
 * in the first place.
 *
 * "Department / Unit" has no real per-user field yet, so it's inferred
 * from role (see $roleMeta below) rather than fabricated per row.
 * "Last Login" IS real — Firestore has no such field, but Firebase Auth
 * does (UserMetaData::$lastLoginAt), fetched per user via
 * $firebaseAuth->getUser(). "Status" reads the real "status" field
 * when a user doc has one, defaulting to "active" for the handful of
 * older docs from before that field existed. "inactive" is now a real,
 * reachable state too — see api/suspend-user.php's header comment.
 *
 * The search box and Role/Status filters now really filter $users
 * (they had no <form> around them at all before — not even inert,
 * literally unsubmittable). There's no Delete action any more — a
 * sub-admin can only put a reversible hold on an account (Suspend/Lock,
 * each with its own way back: Reactivate/Unlock), all from the single
 * "Manage User" modal each row's Actions button opens — see
 * subadmin/user_management.php's header comment for the full picture.
 * "+ Add User" is real now too (api/create-user.php). There's
 * deliberately no Edit action — editing a user account isn't a
 * sub-admin capability.
 *
 * $pendingAccounts (self-registered accounts awaiting approval — see
 * register.php) is separate from $allUsers/$users above: a pending
 * account is excluded from the main staff-directory list/counts
 * (it isn't really "active" or "inactive" yet) and shown in its own
 * "Pending Approvals" table instead, with real Approve/Reject actions
 * (api/approve-account.php, api/reject-account.php) — deciding on
 * these requests is a sub-admin capability.
 *
 * FACILITY ISOLATION: $rawUsers (and everything derived from it below,
 * including $pendingAccounts) is filtered to $adminProfile['facility']
 * right after the read — a sub-admin manages their own facility's
 * staff directory, not every facility's combined. Resolved server-side
 * from this sub-admin's own real users/{uid} doc, never a
 * client-supplied value.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore, $firebaseAuth
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        'doctor' => 'Doctor',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Role -> display label / badge color / inferred department. Only the
// roles that actually exist in this app (see this file's header comment).
// ---------------------------------------------------------------------
$roleMeta = [
    'subadmin'           => ['label' => 'Sub-Admin',           'bg' => '#FCE4E4', 'color' => '#D93025', 'department' => 'Administration'],
    'healthcare_staff'   => ['label' => 'Medical Staff',       'bg' => '#E4F7EA', 'color' => '#1E8E3E', 'department' => 'Medical'],
    'pharmacist'         => ['label' => 'Pharmacist',          'bg' => '#E8E9FD', 'color' => '#4F46E5', 'department' => 'Pharmacy'],
    'medical_technician' => ['label' => 'Medical Technologist', 'bg' => '#E8F0FE', 'color' => '#1A73E8', 'department' => 'Laboratory'],
    'doctor'             => ['label' => 'Doctor',              'bg' => '#FEF3C7', 'color' => '#B45309', 'department' => 'Medical'],
];

$roleOptions = ['All Roles', 'Sub-Admin', 'Medical Staff', 'Pharmacist', 'Medical Technologist', 'Doctor'];
$userStatusOptions = ['All Statuses', 'Active', 'Inactive', 'Locked'];

// Status key -> badge meta.
$userStatusMeta = [
    'active'   => ['label' => 'Active',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'inactive' => ['label' => 'Inactive', 'bg' => '#FCE4E4', 'color' => '#D93025'],
    // Set for real now by includes/shared/login_lockout.php after 7
    // failed sign-in attempts — cleared only by api/unlock-user.php.
    'locked'   => ['label' => 'Locked',   'bg' => '#FCE4E4', 'color' => '#D93025'],
];

// Avatar background palette, cycled per row for visual variety since
// there are no real profile photos yet.
$avatarPalette = [
    ['bg' => '#F1E3FB', 'color' => '#8B5CF6'],
    ['bg' => '#DCEBFC', 'color' => '#1A73E8'],
    ['bg' => '#FCE4E4', 'color' => '#D93025'],
    ['bg' => '#FDECDD', 'color' => '#E8710A'],
];

// ---------------------------------------------------------------------
// Real user list — every users/{uid} doc whose role is one this page
// manages (excludes "patient" — see header comment). Last Login comes
// from Firebase Auth per uid; a lookup failure for any one user
// (network hiccup, since-deleted Auth record, etc.) just falls back to
// "Unknown" for that row instead of breaking the whole page.
// ---------------------------------------------------------------------
$rawUsers = array_values(array_filter(
    $firestore->listDocuments('users'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $adminProfile['facility']
));

$allUsers = [];
foreach ($rawUsers as $doc) {
    $fields = $doc['fields'];
    $role = $fields['role'] ?? '';
    if (!isset($roleMeta[$role])) {
        continue; // not a role this staff directory manages
    }
    if (($fields['status'] ?? 'active') === 'pending') {
        continue; // shown in the separate Pending Approvals table below instead
    }

    $lastLoginLabel = 'Never';
    try {
        $authUser = $firebaseAuth->getUser($doc['id']);
        if ($authUser->metadata->lastLoginAt !== null) {
            $lastLoginLabel = $authUser->metadata->lastLoginAt->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('M j, Y g:i A');
        }
    } catch (\Throwable $e) {
        $lastLoginLabel = 'Unknown';
    }

    $allUsers[] = [
        'id' => $doc['id'],
        'name' => $fields['name'] ?? '(no name)',
        'email' => $fields['email'] ?? '',
        'role' => $role,
        'department' => $roleMeta[$role]['department'],
        'status' => in_array($fields['status'] ?? 'active', ['inactive', 'locked'], true) ? $fields['status'] : 'active',
        'lastLogin' => $lastLoginLabel,
    ];
}

usort($allUsers, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

// ---------------------------------------------------------------------
// Pending Approvals — self-registered accounts (register.php,
// api/register.php) awaiting a sub-admin's decision. Approving/
// rejecting requests is a sub-admin capability, not admin's — see
// api/approve-account.php / api/reject-account.php's header comments.
// api/approve-account.php flips status to "active" (real access
// begins); api/reject-account.php deletes the request entirely (both
// the Firestore doc and the Firebase Auth account) since there's no
// "rejected" state elsewhere in this app to leave it in. jobTitle is
// what the requester actually picked (e.g. "Doctor"); role is what
// that maps to internally (e.g. healthcare_staff).
// ---------------------------------------------------------------------
$pendingAccounts = [];
foreach ($rawUsers as $doc) {
    $f = $doc['fields'];
    if (($f['status'] ?? '') !== 'pending') {
        continue;
    }
    $pendingAccounts[] = [
        'uid' => $doc['id'],
        'name' => $f['name'] ?? '(no name)',
        'email' => $f['email'] ?? '—',
        'phone' => $f['phone'] ?? '—',
        'jobTitle' => $f['jobTitle'] ?? ucfirst($f['role'] ?? 'Unknown'),
        'requestedAtRaw' => $f['createdAt'] ?? '',
        'requestedAt' => isset($f['createdAt']) ? date('M j, Y g:i A', strtotime($f['createdAt'])) : '—',
    ];
}
usort($pendingAccounts, fn ($a, $b) => strcmp($b['requestedAtRaw'], $a['requestedAtRaw']));

foreach ($allUsers as $i => &$u) {
    $u['avatar'] = $avatarPalette[$i % count($avatarPalette)];
}
unset($u);

// ---------------------------------------------------------------------
// User Management Summary — real counts/percentages over $allUsers.
// ---------------------------------------------------------------------
$totalUsersCount = count($allUsers);
$activeUsersCount = count(array_filter($allUsers, fn ($u) => $u['status'] === 'active'));
$inactiveUsersCount = $totalUsersCount - $activeUsersCount;
$distinctRolesCount = count(array_unique(array_column($allUsers, 'role')));

$userManagementSummary = [
    'totalUsers' => $totalUsersCount,
    'activeUsers' => $activeUsersCount,
    'activePct' => $totalUsersCount > 0 ? ($activeUsersCount / $totalUsersCount) * 100 : 0,
    'inactiveUsers' => $inactiveUsersCount,
    'inactivePct' => $totalUsersCount > 0 ? ($inactiveUsersCount / $totalUsersCount) * 100 : 0,
    'roles' => $distinctRolesCount,
];

// ---------------------------------------------------------------------
// Apply the search box (?q=) and Role/Status filters (?role=, ?status=)
// for real.
// ---------------------------------------------------------------------
$searchTerm = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? 'All Roles';
$statusFilter = $_GET['status'] ?? 'All Statuses';

$filteredUsers = array_values(array_filter($allUsers, function ($u) use ($searchTerm, $roleFilter, $statusFilter, $roleMeta) {
    if ($searchTerm !== '' && !hc_fuzzy_match($u['name'] . ' ' . $u['email'] . ' ' . $roleMeta[$u['role']]['label'], $searchTerm)) {
        return false;
    }
    if ($roleFilter !== 'All Roles' && $roleMeta[$u['role']]['label'] !== $roleFilter) {
        return false;
    }
    if ($statusFilter !== 'All Statuses' && ucfirst($u['status']) !== $statusFilter) {
        return false;
    }
    return true;
}));

// ---------------------------------------------------------------------
// Pagination — real total/page-count from the filtered result above.
// ---------------------------------------------------------------------
$usersPerPage = 8;
$usersPagination = hc_paginate($filteredUsers, 'page', $usersPerPage);
$users = $usersPagination['items'];
$usersTotal = $usersPagination['total'];
$usersTotalPages = $usersPagination['totalPages'];
$usersCurrentPage = $usersPagination['page'];
