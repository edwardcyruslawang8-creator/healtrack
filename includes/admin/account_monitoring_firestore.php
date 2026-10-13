<?php
/**
 * =====================================================================
 * Account Monitoring — Data
 * ---------------------------------------------------------------------
 * Every count/chart/row here is derived from Firestore's real "users"
 * collection (all roles — admin, subadmin, healthcare_staff, patient,
 * whatever's actually present), including the real, searchable/
 * filterable/paginated "All User Accounts" table ($allAccountRows /
 * $accountRowsPage / hc_acct_query()) and the real "Accounts Over Time"
 * trend ($accountsOverTime / hc_acm_trend_query() — see that block's
 * own comment for exactly what it's really counting, since no daily
 * status snapshots exist to build a true point-in-time history from).
 * One exception: "Password Expiring Soon" has no backing data source
 * anywhere in the app yet (there's no password-expiry policy), so that
 * specifically stays at 0 rather than being faked — everything else on
 * this page is real. (A "Recent Account Activities" feed — the latest 5
 * audit_logs entries — used to live here too; removed by request, since
 * admin/audit_logs.php already covers that same feed in full.)
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/avatar_helpers.php';       // derive_initials(), derive_avatar_color()
require_once __DIR__ . '/../shared/fuzzy_search.php'; // hc_fuzzy_match()

// Read from the PHP session api/session-login.php populated after Firebase sign-in
// (account_monitoring.php calls session_start() before requiring this file).
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        'doctor' => 'Doctor',
        default => 'Administrator',
    },
];

// ---------------------------------------------------------------------
// Every account in the system, across every role — Firestore's REST API
// (via firebase/FirestoreRestClient.php) doesn't support server-side
// grouping, so this counts by status/role in PHP — fine at this app's scale.
// ---------------------------------------------------------------------
$allUsers = $firestore->listDocuments('users');

$statusCounts = ['active' => 0, 'inactive' => 0, 'locked' => 0, 'pending' => 0];
$roleCounts = [];
foreach ($allUsers as $doc) {
    $status = $doc['fields']['status'] ?? 'active';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
    $role = $doc['fields']['role'] ?? 'other';
    $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
}
$totalAccounts = count($allUsers);
$pct = fn (int $count) => $totalAccounts > 0 ? round($count / $totalAccounts * 100, 1) : 0.0;

// ---------------------------------------------------------------------
// Summary stat cards
// ---------------------------------------------------------------------
$accountStats = [
    ['icon' => 'bi-people-fill',       'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Accounts',      'value' => $totalAccounts,            'sub' => 'All accounts'],
    ['icon' => 'bi-person-check-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Active Accounts',     'value' => $statusCounts['active'],   'sub' => $pct($statusCounts['active']) . '% of total'],
    ['icon' => 'bi-person-dash-fill',  'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Inactive Accounts',   'value' => $statusCounts['inactive'], 'sub' => $pct($statusCounts['inactive']) . '% of total'],
    ['icon' => 'bi-lock-fill',         'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Locked Accounts',     'value' => $statusCounts['locked'],   'sub' => $pct($statusCounts['locked']) . '% of total'],
    ['icon' => 'bi-envelope-fill',     'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Pending Invitations', 'value' => $statusCounts['pending'],  'sub' => $pct($statusCounts['pending']) . '% of total'],
];

// ---------------------------------------------------------------------
// Account Status Overview — doughnut chart
// ---------------------------------------------------------------------
$accountStatusTotal = $totalAccounts;
$accountStatusOverview = [
    ['label' => 'Active',              'value' => $statusCounts['active'],   'pct' => $pct($statusCounts['active']),   'color' => '#1E8E3E'],
    ['label' => 'Inactive',            'value' => $statusCounts['inactive'], 'pct' => $pct($statusCounts['inactive']), 'color' => '#E8710A'],
    ['label' => 'Locked',              'value' => $statusCounts['locked'],   'pct' => $pct($statusCounts['locked']),   'color' => '#D93025'],
    ['label' => 'Pending Invitations', 'value' => $statusCounts['pending'],  'pct' => $pct($statusCounts['pending']),  'color' => '#7C3AED'],
];

// ---------------------------------------------------------------------
// Accounts Over Time — line chart. Real now: no daily status snapshots
// are kept anywhere in the app, so a true point-in-time history isn't
// possible — instead, for each day in the selected window this counts
// how many of TODAY's accounts already existed by that day (their real
// createdAt, stamped by api/register.php/api/create-subadmin.php, is
// <= that day), broken out by each account's CURRENT status. Accounts
// seeded before this app tracked createdAt at all have none — treated
// as having always existed (counted on every day) rather than silently
// dropped from the trend, so the chart's right-edge totals still match
// $statusCounts above exactly.
// ---------------------------------------------------------------------
$accountsOverTimeRangeOptions = ['7 Days' => 7, '30 Days' => 30, '90 Days' => 90];
$accountsOverTimeRange = $_GET['days'] ?? '7 Days';
if (!isset($accountsOverTimeRangeOptions[$accountsOverTimeRange])) {
    $accountsOverTimeRange = '7 Days';
}
$acmDaysBack = $accountsOverTimeRangeOptions[$accountsOverTimeRange];

$acmRangeLabels = [];
$acmRangeDayStamps = [];
for ($i = $acmDaysBack - 1; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days");
    $acmRangeLabels[] = date('M j', $ts);
    $acmRangeDayStamps[] = date('Y-m-d', $ts);
}

$accountsOverTime = [
    'labels'   => $acmRangeLabels,
    'active'   => array_fill(0, $acmDaysBack, 0),
    'inactive' => array_fill(0, $acmDaysBack, 0),
    'locked'   => array_fill(0, $acmDaysBack, 0),
    'pending'  => array_fill(0, $acmDaysBack, 0),
];
foreach ($allUsers as $doc) {
    $fields = $doc['fields'];
    $status = $fields['status'] ?? 'active';
    if (!isset($accountsOverTime[$status])) {
        continue; // unrecognized status value — not one of the 4 real series
    }
    $createdDate = isset($fields['createdAt']) ? date('Y-m-d', strtotime($fields['createdAt'])) : null;
    foreach ($acmRangeDayStamps as $i => $dayStamp) {
        if ($createdDate === null || $createdDate <= $dayStamp) {
            $accountsOverTime[$status][$i]++;
        }
    }
}

if (!function_exists('hc_acm_trend_query')) {
    /** Builds a ?days=N+Days link for the Accounts Over Time range dropdown. */
    function hc_acm_trend_query(string $range): string
    {
        return http_build_query(['days' => $range]);
    }
}
$accountsOverTimeSeries = [
    ['key' => 'active',   'label' => 'Active',              'color' => '#1E8E3E'],
    ['key' => 'inactive', 'label' => 'Inactive',            'color' => '#E8710A'],
    ['key' => 'locked',   'label' => 'Locked',              'color' => '#D93025'],
    ['key' => 'pending',  'label' => 'Pending Invitations', 'color' => '#7C3AED'],
];

// ---------------------------------------------------------------------
// Account Distribution by Role — doughnut chart. Real role values are
// whatever's actually in Firestore's "users" collection (admin,
// subadmin, healthcare_staff, patient, ...), not the granular
// facility-staff job titles a mockup might show — those aren't a real
// field this app tracks per account.
// ---------------------------------------------------------------------
$roleDisplayMeta = [
    'admin'            => ['label' => 'System Administrator', 'color' => '#1A73E8'],
    'subadmin'         => ['label' => 'Sub-Administrator',    'color' => '#1E8E3E'],
    'healthcare_staff' => ['label' => 'Healthcare Staff',     'color' => '#E8710A'],
    'pharmacist'       => ['label' => 'Pharmacist',           'color' => '#4F46E5'],
    'medical_technician' => ['label' => 'Medical Technologist', 'color' => '#12A6C6'],
    'doctor'           => ['label' => 'Doctor',                'color' => '#B45309'],
    'patient'          => ['label' => 'Patient',              'color' => '#7C3AED'],
    'other'            => ['label' => 'Other',                'color' => '#0E9CA8'],
];
$accountsByRoleTotal = $totalAccounts;
$accountsByRole = [];
foreach ($roleCounts as $role => $count) {
    $meta = $roleDisplayMeta[$role] ?? $roleDisplayMeta['other'];
    $accountsByRole[] = ['label' => $meta['label'], 'value' => $count, 'pct' => $pct($count), 'color' => $meta['color']];
}

// ---------------------------------------------------------------------
// All User Accounts — a real, searchable, paginated list of every
// account in the system (every role, every facility), so a System
// Administrator can actually see individual accounts here, not just
// aggregate counts/charts. Read-only (view-only) — editing/approving/
// unlocking a specific account already has a dedicated real flow
// (Sub-Administrator Management for subadmins; a sub-admin's own User
// Management for their facility's staff), so this stays a monitoring
// list rather than a second place those same writes could happen from.
// ---------------------------------------------------------------------
$allAccountRows = [];
foreach ($allUsers as $doc) {
    $fields = $doc['fields'];
    $role = $fields['role'] ?? 'other';
    $roleMeta = $roleDisplayMeta[$role] ?? $roleDisplayMeta['other'];
    $name = $fields['name'] ?? '(No name)';
    $status = $fields['status'] ?? 'active';
    if (!in_array($status, ['active', 'inactive', 'locked', 'pending'], true)) {
        $status = 'active';
    }
    $allAccountRows[] = [
        'id' => $doc['id'],
        'name' => $name,
        'initials' => derive_initials($name),
        'avatarColor' => derive_avatar_color($doc['id']),
        'email' => $fields['email'] ?? '',
        'roleLabel' => $roleMeta['label'],
        // healthCenter is the System Administrator's own account's
        // equivalent field (see includes/admin/audit_logs_firestore.php's
        // own $adminProfile) — falling back to it means an admin
        // account itself still shows something sensible here instead
        // of a blank facility column.
        'facility' => $fields['facility'] ?? ($fields['healthCenter'] ?? ''),
        'status' => $status,
    ];
}
usort($allAccountRows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$distinctRoleLabels = array_values(array_unique(array_column($allAccountRows, 'roleLabel')));
sort($distinctRoleLabels);
$accountRoleOptions = array_merge(['All Roles'], $distinctRoleLabels);
$accountStatusOptions = ['All Statuses', 'Active', 'Inactive', 'Locked', 'Pending'];

$accountsSearchTerm = trim($_GET['acct_q'] ?? '');
$accountsRoleFilter = $_GET['acct_role'] ?? 'All Roles';
$accountsStatusFilter = $_GET['acct_status'] ?? 'All Statuses';

$filteredAccountRows = array_values(array_filter($allAccountRows, function ($r) use ($accountsSearchTerm, $accountsRoleFilter, $accountsStatusFilter) {
    if ($accountsSearchTerm !== '' && !hc_fuzzy_match($r['name'] . ' ' . $r['email'] . ' ' . $r['facility'], $accountsSearchTerm)) {
        return false;
    }
    if ($accountsRoleFilter !== 'All Roles' && $r['roleLabel'] !== $accountsRoleFilter) {
        return false;
    }
    if ($accountsStatusFilter !== 'All Statuses' && ucfirst($r['status']) !== $accountsStatusFilter) {
        return false;
    }

    return true;
}));

$accountRowsTotal = count($filteredAccountRows);
$accountRowsPerPage = 8;
$accountRowsCurrentPage = max(1, (int) ($_GET['acct_page'] ?? 1));
$accountRowsTotalPages = (int) max(1, ceil($accountRowsTotal / $accountRowsPerPage));
$accountRowsCurrentPage = min($accountRowsCurrentPage, $accountRowsTotalPages);

$accountRowsPage = array_slice($filteredAccountRows, ($accountRowsCurrentPage - 1) * $accountRowsPerPage, $accountRowsPerPage);

// Centered pagination window (1 … current-1, current, current+1 … last)
// — same reasoning as every other real paginated list this session.
$accountRowsPaginationWindow = [];
if ($accountRowsTotalPages <= 7) {
    for ($p = 1; $p <= $accountRowsTotalPages; $p++) {
        $accountRowsPaginationWindow[] = $p;
    }
} else {
    $accountRowsPaginationWindow[] = 1;
    $windowStart = max(2, $accountRowsCurrentPage - 1);
    $windowEnd = min($accountRowsTotalPages - 1, $accountRowsCurrentPage + 1);
    if ($windowStart > 2) {
        $accountRowsPaginationWindow[] = '...';
    }
    for ($p = $windowStart; $p <= $windowEnd; $p++) {
        $accountRowsPaginationWindow[] = $p;
    }
    if ($windowEnd < $accountRowsTotalPages - 1) {
        $accountRowsPaginationWindow[] = '...';
    }
    $accountRowsPaginationWindow[] = $accountRowsTotalPages;
}

if (!function_exists('hc_acct_query')) {
    /** Builds a ?query string carrying every current accounts-table filter except the ones in $overrides — used by the filter/pagination links so picking one never drops the others. */
    function hc_acct_query(array $overrides = []): string
    {
        $params = array_merge([
            'acct_q' => $_GET['acct_q'] ?? '',
            'acct_role' => $_GET['acct_role'] ?? 'All Roles',
            'acct_status' => $_GET['acct_status'] ?? 'All Statuses',
            'acct_page' => $_GET['acct_page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 'All Roles' && $v !== 'All Statuses'));
    }
}

// ---------------------------------------------------------------------
// Accounts Needing Attention
// ---------------------------------------------------------------------
$attentionItems = [
    ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Inactive Accounts',      'desc' => 'Accounts inactive for more than 30 days',        'count' => $statusCounts['inactive'], 'href' => 'account_monitoring.php'],
    ['icon' => 'bi-lock-fill',                 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Locked Accounts',        'desc' => 'Accounts locked due to multiple failed attempts', 'count' => $statusCounts['locked'],   'href' => 'account_monitoring.php'],
    ['icon' => 'bi-envelope-fill',             'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Pending Invitations',    'desc' => 'Self-registered accounts awaiting sub-admin approval', 'count' => $statusCounts['pending'],  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-info-circle-fill',          'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Password Expiring Soon', 'desc' => 'No password-expiry policy is configured yet',    'count' => 0,                         'href' => 'account_monitoring.php'],
];
