<?php
/**
 * =====================================================================
 * Radiologic Technologist — Audit Logs — Data
 * ---------------------------------------------------------------------
 * UNLIKE admin/audit_logs.php (every facility) or
 * includes/subadmin/subadmin_audit_logs_firestore.php (the whole
 * facility), this is scoped to ONE real actorUid — the signed-in radio
 * technologist's own activity only, via
 * includes/shared/audit_log_writer.php's hc_get_my_audit_logs_annotated().
 * A radiologic technologist has no reason to see a doctor's or another
 * technologist's actions, so there's no User or Role filter here at
 * all (there's only ever one possible value: you).
 *
 * The search box, Module filter, a real From/To date range, and
 * pagination are real, same hc_filter_audit_logs() the admin/subadmin
 * pages use. Modeled directly on
 * includes/medical_technician/audit_logs_medtech_firestore.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php';           // $firestore
require_once __DIR__ . '/../shared/avatar_helpers.php';        // format_log_datetime()
require_once __DIR__ . '/../shared/audit_log_writer.php';      // hc_get_my_audit_logs_annotated(), hc_filter_audit_logs()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

// ---------------------------------------------------------------------
// Action type -> badge icon/color lookup, same list every other Audit
// Logs page in this app uses.
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

if (!function_exists('resolveActionMeta')) {
    /** Falls back to a neutral gray badge for any action type not listed above. */
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
// This technologist's OWN real audit log entries, newest first.
// ---------------------------------------------------------------------
$myAuditLogs = hc_get_my_audit_logs_annotated($myUid);

// Module filter dropdown options — every real module that appears
// anywhere in THIS technologist's own log (not just the current page).
$seenModules = [];
foreach ($myAuditLogs as $log) {
    $seenModules[$log['module']] = true;
}
ksort($seenModules, SORT_STRING | SORT_FLAG_CASE);

// ---------------------------------------------------------------------
// Search box (?q=), Module filter (?module=), a real From/To date range
// (?from=/?to=), and pagination — real, applied over this
// technologist's own log above.
// ---------------------------------------------------------------------
$auditSearchTerm = trim($_GET['q'] ?? '');
$auditModuleFilter = $_GET['module'] ?? 'All Modules';
$auditDateFrom = trim($_GET['dateRangeFrom'] ?? '');
$auditDateTo = trim($_GET['dateRangeTo'] ?? '');

$filteredAuditLogs = hc_filter_audit_logs($myAuditLogs, [
    'q' => $auditSearchTerm,
    'module' => $auditModuleFilter,
    'from' => $auditDateFrom,
    'to' => $auditDateTo,
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

// No "user" column here — see header comment.
$auditLogs = array_map(fn ($log) => [
    'datetime' => format_log_datetime($log['timestamp'] ?: null),
    'action' => $log['action'],
    'module' => $log['module'],
    'details' => $log['details'],
    'status' => $log['status'],
], $pageOfLogs);

$filterGroups = [
    ['key' => 'module',    'icon' => 'bi-grid-3x3-gap', 'label' => $auditModuleFilter,    'options' => array_merge(['All Modules'], array_keys($seenModules))],
];

if (!function_exists('hc_audit_query')) {
    /** Builds a ?query string carrying every current filter except the ones in $overrides. */
    function hc_audit_query(array $overrides = []): string
    {
        $params = array_merge([
            'q' => $_GET['q'] ?? '',
            'module' => $_GET['module'] ?? 'All Modules',
            'dateRangeFrom' => $_GET['dateRangeFrom'] ?? '',
            'dateRangeTo' => $_GET['dateRangeTo'] ?? '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null
            && $v !== 'All Modules'));
    }
}
