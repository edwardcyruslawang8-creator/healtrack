<?php
/**
 * =====================================================================
 * Administrator Dashboard — Data
 * ---------------------------------------------------------------------
 * $facilities/$subadmins come from includes/subadmin_management_firestore.php
 * (which itself pulls $facilities from includes/facility_management_firestore.php),
 * reusing those live Firestore queries instead of re-querying the same
 * collections here. $recentActivity reads the same "audit_logs"
 * collection admin/audit_logs.php reads, via includes/audit_log_writer.php's
 * write_audit_log() calls scattered across api/*.php.
 * =====================================================================
 */

require_once __DIR__ . '/subadmin_management_firestore.php'; // $adminProfile, $facilities, $subadmins, $statusCounts
require_once __DIR__ . '/../shared/avatar_helpers.php';            // derive_initials(), derive_avatar_color(), format_log_datetime()

// ---------------------------------------------------------------------
// Summary stat cards
// ---------------------------------------------------------------------
$facilityStatusCounts = ['active' => 0, 'inactive' => 0];
foreach ($facilities as $f) {
    if (isset($facilityStatusCounts[$f['status']])) {
        $facilityStatusCounts[$f['status']]++;
    }
}

// "Active Accounts" spans every role (admin/subadmin/healthcare_staff/
// patient), not just sub-admins — a second, separate query over the
// same "users" collection subadmin_management_firestore.php already filtered
// down to role === 'subadmin'.
$allUsersActiveCount = 0;
foreach ($firestore->listDocuments('users') as $doc) {
    if (($doc['fields']['status'] ?? 'active') === 'active') {
        $allUsersActiveCount++;
    }
}

// A sub-admin with a facility assigned counts toward "Assigned Facilities" —
// counting unique facilityIds rather than sub-admins themselves, in case
// more than one sub-admin is ever assigned to the same facility.
$assignedFacilityIds = array_unique(array_filter(array_column($subadmins, 'facilityId')));

$dashboardStats = [
    [
        'icon' => 'bi-building', 'iconBg' => '#FCE4E4', 'iconColor' => '#C0281C',
        'label' => 'Total Facilities', 'value' => (string) count($facilities),
        'meta' => [
            ['label' => 'Active:', 'value' => (string) $facilityStatusCounts['active'], 'color' => '#1E8E3E'],
            ['label' => 'Inactive:', 'value' => (string) $facilityStatusCounts['inactive'], 'color' => '#D93025'],
        ],
    ],
    [
        'icon' => 'bi-people-fill', 'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED',
        'label' => 'Total Sub-Administrators', 'value' => (string) count($subadmins),
        'meta' => [
            ['label' => 'Active:', 'value' => (string) $statusCounts['active'], 'color' => '#1E8E3E'],
            ['label' => 'Inactive:', 'value' => (string) $statusCounts['inactive'], 'color' => '#D93025'],
        ],
    ],
    [
        'icon' => 'bi-person-check-fill', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E',
        'label' => 'Active Accounts', 'value' => (string) $allUsersActiveCount,
        'meta' => [
            ['label' => 'Across all roles', 'value' => '', 'color' => '#7A7478'],
        ],
    ],
    [
        'icon' => 'bi-link-45deg', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A',
        'label' => 'Assigned Facilities', 'value' => (string) count($assignedFacilityIds),
        'meta' => [
            ['label' => 'Across all sub-admins', 'value' => '', 'color' => '#7A7478'],
        ],
    ],
];

// ---------------------------------------------------------------------
// Facility Overview table — same $facilities as facility_management.php,
// plus each facility's live sub-admin count.
// ---------------------------------------------------------------------
$subAdminCountByFacility = [];
foreach ($subadmins as $s) {
    if ($s['facilityId'] !== '') {
        $subAdminCountByFacility[$s['facilityId']] = ($subAdminCountByFacility[$s['facilityId']] ?? 0) + 1;
    }
}
foreach ($facilities as &$f) {
    $f['subAdmins'] = $subAdminCountByFacility[$f['id']] ?? 0;
}
unset($f);

// $facilitiesTotal was referenced here without ever being assigned
// (silently read as null, i.e. 0) — combined with $facilitiesCurrentPage
// being hardcoded to 1 and $facilities never actually sliced below,
// this table's Previous/page-number/Next links in admin_dashboard.php
// were real dead links (href="#") pointing at nothing. Real now, same
// pattern as every other paginated admin list this session.
$facilitiesPerPage = 5;
$facilitiesTotal = count($facilities);
$facilitiesCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$facilitiesTotalPages = (int) max(1, ceil($facilitiesTotal / $facilitiesPerPage));
$facilitiesCurrentPage = min($facilitiesCurrentPage, $facilitiesTotalPages);

$facilities = array_slice($facilities, ($facilitiesCurrentPage - 1) * $facilitiesPerPage, $facilitiesPerPage);

if (!function_exists('hc_dash_fac_query')) {
    /** Builds a ?page=N query string — this widget has no search/filter of its own, just pagination. */
    function hc_dash_fac_query(int $page): string
    {
        return http_build_query(['page' => $page]);
    }
}

// ---------------------------------------------------------------------
// Sub-Administrator Overview donut chart
// ---------------------------------------------------------------------
$subAdminChart = [
    'total' => count($subadmins),
    'active' => $statusCounts['active'],
    'inactive' => $statusCounts['inactive'],
];

// ---------------------------------------------------------------------
// Recent Sub-Administrator Activity — latest 5 audit log entries scoped
// to the two modules this widget is about (mirrors what a "sub-admin
// activity" feed would filter to once real filtering exists).
// ---------------------------------------------------------------------
$relevantModules = ['Sub-Administrator Management', 'Facility Management'];
$rawLogs = $firestore->listDocuments('audit_logs');
usort($rawLogs, fn ($a, $b) => strcmp($b['fields']['timestamp'] ?? '', $a['fields']['timestamp'] ?? ''));

$recentActivity = [];
foreach ($rawLogs as $doc) {
    $fields = $doc['fields'];
    if (!in_array($fields['module'] ?? '', $relevantModules, true)) {
        continue;
    }

    $recentActivity[] = [
        'name' => $fields['actorName'] ?? 'System',
        'avatarColor' => derive_avatar_color($fields['actorUid'] ?? $fields['actorName'] ?? ''),
        'module' => $fields['module'] ?? '',
        'activity' => $fields['details'] ?? '',
        'datetime' => format_log_datetime($fields['timestamp'] ?? null),
    ];

    if (count($recentActivity) >= 5) {
        break;
    }
}

// ---------------------------------------------------------------------
// Account Status Overview — the same sub-admin status counts as
// subadmin_management.php's stat cards, since this widget sits right
// next to "Recent Sub-Administrator Activity".
// ---------------------------------------------------------------------
$accountStatuses = [
    ['icon' => 'bi-shield-fill-check', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Active Accounts',     'desc' => 'Accounts currently active and accessible', 'value' => $statusCounts['active'],   'valueColor' => '#1E8E3E'],
    ['icon' => 'bi-clock-fill',        'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Inactive Accounts',   'desc' => 'Accounts inactive for more than 30 days',   'value' => $statusCounts['inactive'], 'valueColor' => '#E8710A'],
    ['icon' => 'bi-lock-fill',         'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Locked Accounts',     'desc' => 'Accounts locked due to multiple failed attempts', 'value' => $statusCounts['locked'], 'valueColor' => '#1A73E8'],
    ['icon' => 'bi-person-x-fill',     'iconBg' => '#FCE4E4', 'iconColor' => '#C0281C', 'label' => 'Pending Invitations', 'desc' => 'Invitations sent but not yet accepted',     'value' => $statusCounts['pending'],  'valueColor' => '#C0281C'],
];

// ---------------------------------------------------------------------
// Predictive & Prescriptive Analytics preview card — $predictiveOverviewCards/
// $predictiveOverviewTopAlerts, system-wide (every facility combined).
// See includes/admin/predictive_overview_firestore.php's header comment.
// ---------------------------------------------------------------------
require_once __DIR__ . '/predictive_overview_firestore.php';
