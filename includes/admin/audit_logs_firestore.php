<?php
/**
 * =====================================================================
 * Audit Logs — Data
 * ---------------------------------------------------------------------
 * $auditLogs is read live from Firestore's "audit_logs" collection via
 * includes/shared/audit_log_writer.php's hc_get_all_audit_logs_annotated()
 * (write_audit_log() is what populates it, called from every mutating
 * api/*.php endpoint — see that file's header comment for the full
 * list). admin/audit_logs.php, includes/admin/admin_dashboard_firestore.php's
 * "Recent Sub-Administrator Activity", and includes/admin/account_monitoring_firestore.php's
 * "Recent Account Activities" all read this same collection back.
 *
 * The search box, every filter dropdown (User/Module/Action/Status), the
 * real From/To date range, and pagination are now all real —
 * hc_filter_audit_logs()
 * narrows the FULL real log set (not just whatever happened to be on
 * the current page, which is what the old version's User/Module
 * dropdown options were mistakenly built from) before this page ever
 * slices out one page of results. A System Administrator sees every
 * facility's activity, unfiltered by default — unlike
 * includes/subadmin/subadmin_audit_logs_firestore.php's own copy of
 * this file, which locks the result to the signed-in sub-admin's own
 * facility.
 *
 * $adminProfile mirrors includes/admin/admin_dashboard_firestore.php's version — see the
 * note there on why it's a small duplicate rather than shared.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php';   // $firestore
require_once __DIR__ . '/../shared/avatar_helpers.php';        // derive_initials(), derive_avatar_color(), format_log_datetime()
require_once __DIR__ . '/../shared/audit_log_writer.php'; // hc_get_all_audit_logs_annotated(), hc_filter_audit_logs()

// Read from the PHP session api/session-login.php populated after Firebase sign-in
// (audit_logs.php calls session_start() before requiring this file).
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'admin' => 'System Administrator',
        'subadmin' => 'Sub-Administrator',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Administrator',
    },
    // A System Administrator oversees every facility — this just scopes
    // which one's audit trail the topbar selector currently shows; it's
    // not tied to the signed-in account itself.
    'facility' => 'North Super Health Center',
];

// Facilities list for the topbar facility selector — mirrors
// includes/facility_management_firestore.php's names rather than requiring that
// file directly, since that file also defines its own $adminProfile.
$facilityOptions = [
    'North Super Health Center',
    'East Super Health Center',
    'West Super Health Center',
];

// ---------------------------------------------------------------------
// Action type → badge icon/color lookup, so the table loop doesn't
// hardcode styling per row. resolveActionMeta() below falls back to a
// neutral badge for any action type not listed here.
// ---------------------------------------------------------------------
$actionTypeMeta = [
    'created'    => ['label' => 'Created',     'icon' => 'bi-plus-circle-fill',    'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'updated'    => ['label' => 'Updated',     'icon' => 'bi-pencil-fill',         'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'viewed'     => ['label' => 'Viewed',      'icon' => 'bi-eye-fill',            'bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'deleted'    => ['label' => 'Deleted',     'icon' => 'bi-trash-fill',          'bg' => '#FCE4E4', 'color' => '#D93025'],
    'approved'   => ['label' => 'Approved',    'icon' => 'bi-check-circle-fill',   'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'rejected'   => ['label' => 'Rejected',    'icon' => 'bi-x-circle-fill',       'bg' => '#FCE4E4', 'color' => '#D93025'],
    'released'   => ['label' => 'Released',    'icon' => 'bi-box-arrow-up',        'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'logged_in'  => ['label' => 'Logged In',   'icon' => 'bi-box-arrow-in-right',  'bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'logged_out' => ['label' => 'Logged Out',  'icon' => 'bi-box-arrow-right',     'bg' => '#FCE4E4', 'color' => '#D93025'],
];

/**
 * Reusable helper — looks up an action type's badge meta, falling back to
 * a neutral gray badge instead of an undefined-index warning if a future
 * log entry ever carries an action type not yet listed in $actionTypeMeta.
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

// ---------------------------------------------------------------------
// Real audit log entries, newest first — every facility, unfiltered.
// ---------------------------------------------------------------------
$allAuditLogs = hc_get_all_audit_logs_annotated();
$myUid = $_SESSION['uid'] ?? '';

// Driven only by the sidebar's Audit Logs > Admin / Sub Admin / Doctor /
// Medical Staff / Medical Technologist / Radiologic Technologist /
// Pharmacist dropdown (see the nav array below) — not a filter-toolbar
// dropdown of its own, so any value other than these seven just means
// "no role split". Same granular per-role breakdown as
// includes/subadmin/subadmin_audit_logs_firestore.php's own Audit Logs
// dropdown, just system-wide (every facility) here instead of scoped to
// one. Resolved up here (rather than down with the rest of the
// filters, where it used to live) because $seenUsers below needs it
// too. 'admin' (added alongside subadmin's own equivalent "Sub Admin"
// self-scoping this same session) lets a System Administrator see
// THEIR OWN actions specifically — restricted to actorUid === $myUid
// below, same reasoning as subadmin's: if more than one admin account
// ever exists, one shouldn't see another's activity just by switching
// to this tab, any more than two sub-admins should see each other's.
$auditRoleOptions = ['admin', 'subadmin', 'doctor', 'healthcare_staff', 'medical_technician', 'radio_technologist', 'pharmacist'];
$auditRoleFilter = in_array($_GET['role'] ?? '', $auditRoleOptions, true) ? $_GET['role'] : '';
$auditRoleFilterLabels = [
    'admin' => 'Admin',
    'subadmin' => 'Sub-Admin',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Medical Staff',
    'medical_technician' => 'Medical Technologist',
    'radio_technologist' => 'Radiologic Technologist',
    'pharmacist' => 'Pharmacist',
];
// $log['actorRole'] is the real display label write_audit_log() already
// stamps (includes/shared/audit_log_writer.php) — distinct from
// $auditRoleFilterLabels above only where that label differs from the
// role-filter's own nav label (Healthcare Staff's real actorRole vs.
// this page's "Medical Staff" nav wording).
$auditRoleActorRoles = [
    'admin' => 'System Administrator',
    'subadmin' => 'Sub-Administrator',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'medical_technician' => 'Medical Technologist',
    'radio_technologist' => 'Radiologic Technologist',
    'pharmacist' => 'Pharmacist',
];

// User/Module filter dropdown options — every real name/module that
// appears ANYWHERE in the CURRENT role scope (not just the current
// page), so picking "Dr. Ramon Villareal" from the User filter
// actually works even if their most recent action is several pages
// back. Scoped to $auditRoleFilter so the "All Users" dropdown (and
// the search bar's own autocomplete, which reuses $seenUsers) never
// offers a name that can't actually match anything once combined with
// the active role section — e.g. while on Audit Logs > Sub Admin, this
// only ever lists real Sub-Administrator names, never a doctor/
// pharmacist/healthcare-staff name that would just come back empty. A
// System Administrator's own name is left out unconditionally (not a
// role this page's role split ever exposes a bucket for).
$seenUsers = [];
$seenModules = [];
foreach ($allAuditLogs as $log) {
    $matchesRoleScope = $auditRoleFilter === ''
        ? $log['actorRole'] !== 'System Administrator'
        : $log['actorRole'] === ($auditRoleActorRoles[$auditRoleFilter] ?? null);
    // "Admin" means THIS System Administrator's own activity
    // specifically — see the header comment above $auditRoleOptions for
    // why, same narrowing applied to $filteredAuditLogs below.
    if ($matchesRoleScope && $auditRoleFilter === 'admin') {
        $matchesRoleScope = $log['actorUid'] === $myUid;
    }
    if ($matchesRoleScope) {
        $seenUsers[$log['actorName']] = true;
        // Scoped to the active role section too (previously unconditional,
        // so "All Modules" always listed every module system-wide no
        // matter which role tab was active — same fix already applied to
        // includes/subadmin/subadmin_audit_logs_firestore.php's own copy
        // of this loop earlier this session).
        $seenModules[$log['module']] = true;
    }
}
ksort($seenUsers, SORT_STRING | SORT_FLAG_CASE);
ksort($seenModules, SORT_STRING | SORT_FLAG_CASE);

// ---------------------------------------------------------------------
// Search box (?q=) and filter dropdowns (?user=, ?module=, ?action=,
// ?status=, ?from=, ?to=) — all real now, applied over the full set
// above before pagination.
// ---------------------------------------------------------------------
$auditSearchTerm = trim($_GET['q'] ?? '');
$auditUserFilter = $_GET['user'] ?? 'All Users';
$auditModuleFilter = $_GET['module'] ?? 'All Modules';
$auditActionFilter = $_GET['action'] ?? 'All Actions';
$auditStatusFilter = $_GET['status'] ?? 'All Statuses';
// Both blank by default — every real date span, not a preset Today/
// This Week/This Month bucket; either end can be left open-ended.
// Always-visible From/To inputs (admin/audit_logs.php), not a dropdown.
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

// "Admin" is scoped to THIS System Administrator's own account
// specifically — see the header comment above $auditRoleOptions for
// why. hc_filter_audit_logs() has no notion of "the currently
// signed-in account" to apply this itself, so it's applied here
// instead, specific to this one page (same pattern
// includes/subadmin/subadmin_audit_logs_firestore.php's own "Sub
// Admin" option already uses).
if ($auditRoleFilter === 'admin') {
    $filteredAuditLogs = array_values(array_filter($filteredAuditLogs, fn ($log) => $log['actorUid'] === $myUid));
}

$auditRoleFilterLabel = $auditRoleFilterLabels[$auditRoleFilter] ?? '';

// ---------------------------------------------------------------------
// Real pagination over the filtered result.
// ---------------------------------------------------------------------
$auditLogsTotal = count($filteredAuditLogs);
$auditLogsPerPage = 10;
$auditLogsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
$auditLogsTotalPages = (int) max(1, ceil($auditLogsTotal / $auditLogsPerPage));
$auditLogsCurrentPage = min($auditLogsCurrentPage, $auditLogsTotalPages);

$pageOfLogs = array_slice($filteredAuditLogs, ($auditLogsCurrentPage - 1) * $auditLogsPerPage, $auditLogsPerPage);

// Centered pagination window (1 … current-1, current, current+1 … last)
// — a real log set can run to many pages, so a fixed "always show 1,
// 2, 3" window would strand anyone past page 3 with no numbered link
// anywhere near where they actually are.
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
// User/Module lists are the real, complete set from above (not just
// whatever's on the current page).
//
// No Action/Status dropdown here by request — $auditActionFilter/
// $auditStatusFilter and hc_filter_audit_logs()'s own handling of them
// are left in place (so a direct ?action=/?status= link still works,
// and hc_audit_query() still carries an existing one through), just
// with no toolbar control to set them from this page.
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
            // Was ['subadmin', 'user'] — a stale whitelist missing 5 of
            // the real 7 role-filter values (see $auditRoleOptions
            // above, which this can't reference directly: a named
            // function has no access to the including script's
            // variables), so a Module/User/etc. filter click used to
            // silently drop e.g. "?role=doctor" the moment it was
            // combined with any other filter.
            'role' => in_array($_GET['role'] ?? '', ['admin', 'subadmin', 'doctor', 'healthcare_staff', 'medical_technician', 'radio_technologist', 'pharmacist'], true) ? $_GET['role'] : '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null
            && $v !== 'All Users' && $v !== 'All Modules' && $v !== 'All Actions'
            && $v !== 'All Statuses'));
    }
}
