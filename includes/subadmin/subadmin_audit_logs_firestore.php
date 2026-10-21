<?php
/**
 * =====================================================================
 * Sub-Admin Audit Logs — Data
 * ---------------------------------------------------------------------
 * Static-config + LIVE data for subadmin/audit_logs.php. $auditLogs
 * reads from the same real Firestore "audit_logs" collection
 * includes/admin/audit_logs_firestore.php reads (via
 * includes/shared/audit_log_writer.php's hc_get_all_audit_logs_annotated()),
 * but — UNLIKE the admin version — is locked to this sub-admin's own
 * real facility (resolved server-side from their own users/{uid} doc,
 * never a client-supplied value) right after the read, before search/
 * filter/pagination ever run: a sub-admin sees their own facility's
 * full activity trail, not every facility's combined. This closes the
 * gap this file's own comment used to flag ("no facility-scoping on
 * that collection yet") the same way the rest of this app's
 * facility-isolation work already does for patients/consultations/etc.
 *
 * The search box and every filter dropdown (User/Module/Action/Status/
 * Date Range) are real now, same as the admin version — see that
 * file's own header comment for what changed.
 *
 * Deliberately a separate file from includes/admin/audit_logs_firestore.php
 * rather than requiring it directly: that file defines its own
 * $adminProfile with admin-section role labels ("System
 * Administrator") and shows every facility unfiltered, both wrong for
 * this page — every subadmin/*.php page instead derives $adminProfile
 * from the signed-in sub-admin's own Firestore users/{uid} document
 * (see includes/subadmin/user_management_firestore.php and its siblings for the
 * identical pattern). $actionTypeMeta/resolveActionMeta()/avatar
 * helpers are small enough to duplicate here rather than share across
 * the admin/subadmin boundary.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php';   // $firestore
require_once __DIR__ . '/../shared/avatar_helpers.php';        // derive_initials(), derive_avatar_color(), format_log_datetime()
require_once __DIR__ . '/../shared/audit_log_writer.php'; // hc_get_all_audit_logs_annotated(), hc_filter_audit_logs()

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

$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

if (!function_exists('resolveActionMeta')) {
    /**
     * Reusable helper — looks up an action type's badge meta, falling back
     * to a neutral gray badge instead of an undefined-index warning if a
     * future log entry ever carries an action type not yet listed in
     * $actionTypeMeta.
     */
    function resolveActionMeta(array $actionTypeMeta, string $type): array
    {
        return $actionTypeMeta[$type] ?? [
            'label' => ucfirst(str_replace('_', ' ', $type)),
            'icon' => 'bi-info-circle-fill',
            'bg' => '#EEF0F3',
            'color' => '#6B7280',
        ];
    }
}

// ---------------------------------------------------------------------
// Action type -> badge icon/color lookup, so the table loop doesn't
// hardcode styling per row.
// ---------------------------------------------------------------------
$actionTypeMeta = [
    'created'    => ['label' => 'Created',     'icon' => 'bi-plus-circle-fill',    'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'updated'    => ['label' => 'Updated',     'icon' => 'bi-pencil-fill',         'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'viewed'     => ['label' => 'Viewed',      'icon' => 'bi-eye-fill',            'bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'deleted'    => ['label' => 'Deleted',     'icon' => 'bi-trash-fill',          'bg' => '#FCE4E4', 'color' => '#D93025'],
    'approved'   => ['label' => 'Approved',    'icon' => 'bi-check-circle-fill',   'bg' => '#FDECDD', 'color' => '#E8710A'],
    'rejected'   => ['label' => 'Rejected',    'icon' => 'bi-x-circle-fill',       'bg' => '#FCE4E4', 'color' => '#D93025'],
    'released'   => ['label' => 'Released',    'icon' => 'bi-box-arrow-up',        'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'logged_in'  => ['label' => 'Logged In',   'icon' => 'bi-box-arrow-in-right',  'bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'logged_out' => ['label' => 'Logged Out',  'icon' => 'bi-box-arrow-right',     'bg' => '#FCE4E4', 'color' => '#D93025'],
];

// ---------------------------------------------------------------------
// Real audit log entries, newest first — locked to this sub-admin's own
// real facility (see header comment).
// ---------------------------------------------------------------------
$allAuditLogs = array_values(array_filter(
    hc_get_all_audit_logs_annotated(),
    fn ($log) => $log['facility'] === $adminProfile['facility']
));

// Driven only by the sidebar's Audit Logs > Doctor/Medical Staff/
// Medical Technologist/Pharmacist dropdown (see the nav array in
// subadmin/audit_logs.php) — not a filter-toolbar dropdown of its own,
// so any value other than these four just means "no role split".
// Resolved up here (rather than down with the rest of the filters)
// because $seenUsers below needs it too.
$auditRoleOptions = ['doctor', 'healthcare_staff', 'medical_technician', 'radio_technologist', 'pharmacist'];
$auditRoleFilter = in_array($_GET['role'] ?? '', $auditRoleOptions, true) ? $_GET['role'] : '';
$auditRoleFilterLabels = [
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Medical Staff',
    'medical_technician' => 'Medical Technologist',
    'radio_technologist' => 'Radio Technologist',
    'pharmacist' => 'Pharmacist',
];
$auditRoleFilterLabel = $auditRoleFilterLabels[$auditRoleFilter] ?? '';
$auditRoleActorRoles = [
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'medical_technician' => 'Medical Technologist',
    'radio_technologist' => 'Radio Technologist',
    'pharmacist' => 'Pharmacist',
];

// User/Module filter dropdown options — every real name/module that
// appears ANYWHERE in the CURRENT role scope (not just the current
// page). Scoped to $auditRoleFilter so the "All Users" dropdown never
// offers a name that can't actually match anything once combined with
// the active role section — e.g. while on Audit Logs > Doctor, this
// only ever lists real Doctor names at this facility. A System
// Administrator's own name is left out unconditionally either way —
// see includes/admin/audit_logs_firestore.php's own copy of this
// comment for why. Belt-and-suspenders here since a System
// Administrator's own users/{uid} doc carries no "facility" field
// (only "healthCenter"), so hc_get_all_audit_logs_annotated()'s join
// already leaves their entries out of $allAuditLogs above.
$seenUsers = [];
$seenModules = [];
foreach ($allAuditLogs as $log) {
    $matchesRoleScope = isset($auditRoleActorRoles[$auditRoleFilter])
        ? $log['actorRole'] === $auditRoleActorRoles[$auditRoleFilter]
        : $log['actorRole'] !== 'System Administrator';
    if ($matchesRoleScope) {
        $seenUsers[$log['actorName']] = true;
    }
    $seenModules[$log['module']] = true;
}
ksort($seenUsers, SORT_STRING | SORT_FLAG_CASE);
ksort($seenModules, SORT_STRING | SORT_FLAG_CASE);

// ---------------------------------------------------------------------
// Search box (?q=) and filter dropdowns — real, applied over this
// sub-admin's own facility-scoped set above before pagination.
// ---------------------------------------------------------------------
$auditSearchTerm = trim($_GET['q'] ?? '');
$auditUserFilter = $_GET['user'] ?? 'All Users';
$auditModuleFilter = $_GET['module'] ?? 'All Modules';
$auditActionFilter = $_GET['action'] ?? 'All Actions';
$auditStatusFilter = $_GET['status'] ?? 'All Statuses';
// Both blank by default — every real date span, not a preset Today/
// This Week/This Month bucket; either end can be left open-ended.
// Always-visible From/To inputs (subadmin/audit_logs.php), not a dropdown.
$auditDateFrom = trim($_GET['dateRangeFrom'] ?? '');
$auditDateTo = trim($_GET['dateRangeTo'] ?? '');

$filteredAuditLogs = hc_filter_audit_logs($allAuditLogs, [
    'q' => $auditSearchTerm,
    'user' => $auditUserFilter,
    'module' => $auditModuleFilter,
    'action' => $auditActionFilter,
    'status' => $auditStatusFilter,
    'from' => $auditDateFrom,
    'to' => $auditDateTo,
    'role' => $auditRoleFilter,
]);

// ---------------------------------------------------------------------
// Real pagination over the filtered result.
// ---------------------------------------------------------------------
$auditLogsTotal = count($filteredAuditLogs);
$auditLogsPerPage = 10;
$auditLogsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$auditLogsTotalPages = (int) max(1, ceil($auditLogsTotal / $auditLogsPerPage));
$auditLogsCurrentPage = min($auditLogsCurrentPage, $auditLogsTotalPages);

$pageOfLogs = array_slice($filteredAuditLogs, ($auditLogsCurrentPage - 1) * $auditLogsPerPage, $auditLogsPerPage);

// Centered pagination window — see includes/admin/audit_logs_firestore.php's
// own copy of this for why a fixed "always show 1, 2, 3" window isn't enough.
$auditPaginationWindow = [];
if ($auditLogsTotalPages <= 7) {
    for ($p = 1; $p <= $auditLogsTotalPages; $p++) {
        $auditPaginationWindow[] = $p;
    }
} else {
    $auditPaginationWindow[] = 1;
    $windowStart = max(2, $auditLogsCurrentPage - 1);
    $windowEnd = min($auditLogsTotalPages - 1, $auditLogsCurrentPage + 1);
    if ($windowStart > 2) {
        $auditPaginationWindow[] = '...';
    }
    for ($p = $windowStart; $p <= $windowEnd; $p++) {
        $auditPaginationWindow[] = $p;
    }
    if ($windowEnd < $auditLogsTotalPages - 1) {
        $auditPaginationWindow[] = '...';
    }
    $auditPaginationWindow[] = $auditLogsTotalPages;
}

$auditLogs = array_map(fn ($log) => [
    'datetime' => format_log_datetime($log['timestamp'] ?: null),
    'user' => $log['actorName'],
    'role' => $log['actorRole'],
    'initials' => derive_initials($log['actorName']),
    'avatarColor' => derive_avatar_color($log['actorUid'] ?: $log['actorName']),
    'action' => $log['action'],
    'module' => $log['module'],
    'details' => $log['details'],
    'status' => $log['status'],
], $pageOfLogs);

// ---------------------------------------------------------------------
// Filter toolbar — each group renders as a dropdown of $options. The
// User/Module lists are the real, complete set from this sub-admin's
// own facility (not just whatever's on the current page).
//
// No Action/Status dropdown here by request — see
// includes/admin/audit_logs_firestore.php's own copy of this comment.
// ---------------------------------------------------------------------
$filterGroups = [
    ['key' => 'user',      'icon' => 'bi-person',          'label' => $auditUserFilter,      'options' => array_merge(['All Users'], array_keys($seenUsers))],
    ['key' => 'module',    'icon' => 'bi-grid-3x3-gap',    'label' => $auditModuleFilter,    'options' => array_merge(['All Modules'], array_keys($seenModules))],
];

if (!function_exists('hc_audit_query')) {
    /** Builds a ?query string carrying every current filter except the ones in $overrides — used by filter links/pagination so picking one filter or paging never drops the others. */
    function hc_audit_query(array $overrides = []): string
    {
        $params = array_merge([
            'q' => $_GET['q'] ?? '',
            'user' => $_GET['user'] ?? 'All Users',
            'module' => $_GET['module'] ?? 'All Modules',
            'action' => $_GET['action'] ?? 'All Actions',
            'status' => $_GET['status'] ?? 'All Statuses',
            'dateRangeFrom' => $_GET['dateRangeFrom'] ?? '',
            'dateRangeTo' => $_GET['dateRangeTo'] ?? '',
            'role' => in_array($_GET['role'] ?? '', ['doctor', 'healthcare_staff', 'medical_technician', 'radio_technologist', 'pharmacist'], true) ? $_GET['role'] : '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null
            && $v !== 'All Users' && $v !== 'All Modules' && $v !== 'All Actions'
            && $v !== 'All Statuses'));
    }
}
