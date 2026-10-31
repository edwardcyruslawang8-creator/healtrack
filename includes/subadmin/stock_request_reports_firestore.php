<?php
/**
 * =====================================================================
 * Stock Request Reports — Data
 * ---------------------------------------------------------------------
 * The "Stock Request Reports" tab of the Analytics & Reports section —
 * a sibling page to subadmin/analytics_reports.php ("Overview"),
 * subadmin/patient_reports.php ("Patient Reports"), and
 * subadmin/inventory_reports.php ("Inventory Reports"), sharing their
 * $adminProfile/$facilityOptions/$analyticsTabs shape.
 *
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own real
 * assigned facility (real "facility" field on "stock_requests") — this
 * was previously reading ALL facilities' stock requests system-wide, a
 * scoping gap fixed here to match the rest of this app's subadmin pages
 * (same fix applied to includes/subadmin/patient_reports_firestore.php
 * this same pass). The stat cards (Total/Approved/Pending/Rejected/
 * Fulfilled Requests), Requests by Status, Requests by Category
 * (Medicines vs. Vaccines — the only two types the real "stock_requests"
 * collection tracks; "Medical Supplies"/"Other" don't exist as real
 * categories, so they're dropped rather than faked), and the Recent
 * Stock Requests table are all read from the real "stock_requests"
 * collection (same source as includes/stock_requests_firestore.php).
 * "Fulfilled" maps to the real 'released' status value.
 *
 * The filter toolbar is now real: date range filters by the real
 * "requestedAt" field; status maps to the real 4-value status field;
 * category maps to the real "type" field; "Requested By" is populated
 * from the real distinct pharmacistName values seen at this facility
 * (not a hardcoded example list) and filters by that same field. Recent
 * Stock Requests has real pagination (includes/shared/pagination_helpers.php)
 * and both tables on this page have real click-to-sort columns
 * (assets/js/sortable-table.js).
 *
 * STILL FRONT-END ONLY, deliberately: the Request Fulfillment Summary
 * table's partial/unfulfilled breakdown — the real data only has 4
 * flat statuses (pending/approved/released/rejected), no concept of a
 * request being "partially" fulfilled, so that finer-grained split
 * can't be derived without inventing a field that doesn't reflect
 * anything real.
 *
 * NOTE on $requestsByStatus: unlike the original placeholder (which
 * deliberately made Approved/Pending/Rejected/Fulfilled NOT sum to the
 * total, treating "Fulfilled" as a non-exclusive downstream stage of
 * "Approved"), the real "stock_requests" collection's status field is
 * a single mutually-exclusive value per request (pending/approved/
 * released/rejected) — so these 4 real counts DO sum to $requestsTotal.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/pagination_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/predictive_analytics_firestore.php'; // $facility, $facilityName — locked to THIS sub-admin's own real facility

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        default => 'Sub-Administrator',
    },
    'facility' => $facilityName,
];

$analyticsDateRangeLabel = date('F j, Y'); // real, up-to-date single date (was a frozen placeholder date)
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Analytics tabs — each is its own page (not client-side tab-switching).
// Stock Request Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php', 'active' => true],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'lab_xray_reports',      'label' => 'Lab & X-ray Reports',   'href' => 'lab_xray_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// Real stock requests at THIS facility only, "Requested By" options
// derived from every request at this facility (before this page's own
// filters below are applied, so the dropdown itself never loses an
// option just because it's currently filtered to something else).
// ---------------------------------------------------------------------
$facilitySrReports = array_values(array_filter(
    $firestore->listDocuments('stock_requests'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));
$seenRequesters = array_unique(array_filter(array_map(fn ($doc) => $doc['fields']['pharmacistName'] ?? '', $facilitySrReports)));
sort($seenRequesters);

// ---------------------------------------------------------------------
// Filter toolbar dropdown options — all real now (see header comment).
// ---------------------------------------------------------------------
$statusFilterOptions = ['All Status', 'Approved', 'Pending', 'Rejected', 'Fulfilled'];
$categoryFilterOptions = ['All Categories', 'Medicines', 'Vaccines'];
$requestedByFilterOptions = array_merge(['All Requested By'], $seenRequesters);

$srDateFilter = hc_resolve_date_range_filter();
$srStatusFilter = (string) ($_GET['status'] ?? '');
$srCategoryFilter = (string) ($_GET['category'] ?? '');
$srRequestedByFilter = (string) ($_GET['requestedBy'] ?? '');

$srStatusToReal = ['Approved' => 'approved', 'Pending' => 'pending', 'Rejected' => 'rejected', 'Fulfilled' => 'released'];
$srCategoryToType = ['Medicines' => 'medicine', 'Vaccines' => 'vaccine'];

$rawSrReports = array_values(array_filter($facilitySrReports, function ($doc) use ($srDateFilter, $srStatusFilter, $srCategoryFilter, $srRequestedByFilter, $srStatusToReal, $srCategoryToType) {
    $fields = $doc['fields'];
    if (!hc_date_in_range($fields['requestedAt'] ?? null, $srDateFilter)) {
        return false;
    }
    if ($srStatusFilter !== '' && ($fields['status'] ?? '') !== ($srStatusToReal[$srStatusFilter] ?? '__none__')) {
        return false;
    }
    if ($srCategoryFilter !== '' && ($fields['type'] ?? 'medicine') !== ($srCategoryToType[$srCategoryFilter] ?? '__none__')) {
        return false;
    }
    if ($srRequestedByFilter !== '' && ($fields['pharmacistName'] ?? '') !== $srRequestedByFilter) {
        return false;
    }
    return true;
}));
usort($rawSrReports, fn ($a, $b) => strcmp($b['fields']['requestedAt'] ?? '', $a['fields']['requestedAt'] ?? ''));

$allSrReports = array_map(fn ($doc) => ['docId' => $doc['id']] + $doc['fields'], $rawSrReports);
$requestsTotal = count($allSrReports);
$srPct = fn (int $n) => $requestsTotal > 0 ? round($n / $requestsTotal * 100, 1) : 0;

$approvedCount = count(array_filter($allSrReports, fn ($r) => ($r['status'] ?? '') === 'approved'));
$pendingCount = count(array_filter($allSrReports, fn ($r) => ($r['status'] ?? '') === 'pending'));
$rejectedCount = count(array_filter($allSrReports, fn ($r) => ($r['status'] ?? '') === 'rejected'));
$fulfilledCount = count(array_filter($allSrReports, fn ($r) => ($r['status'] ?? '') === 'released'));

// ---------------------------------------------------------------------
// Summary stat cards — real.
// ---------------------------------------------------------------------
$stockRequestStatCards = [
    ['icon' => 'fa-solid fa-file-medical', 'iconLib' => 'fa', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Requests',    'value' => $requestsTotal,   'sub' => '100% of total requests'],
    ['icon' => 'bi-check-circle-fill',     'iconLib' => 'bi', 'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Approved Requests', 'value' => $approvedCount,   'sub' => $srPct($approvedCount) . '% of total requests'],
    ['icon' => 'bi-clock-fill',            'iconLib' => 'bi', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Pending Requests',  'value' => $pendingCount,    'sub' => $srPct($pendingCount) . '% of total requests'],
    ['icon' => 'bi-x-circle-fill',         'iconLib' => 'bi', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Rejected Requests', 'value' => $rejectedCount,   'sub' => $srPct($rejectedCount) . '% of total requests'],
    ['icon' => 'bi-box-seam-fill',         'iconLib' => 'bi', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Fulfilled Requests','value' => $fulfilledCount,  'sub' => $srPct($fulfilledCount) . '% of total requests'],
];

// ---------------------------------------------------------------------
// Requests by Status (doughnut) — real; see this file's header NOTE.
// ---------------------------------------------------------------------
$requestsByStatus = [
    ['label' => 'Approved',  'value' => $approvedCount,  'pct' => $srPct($approvedCount),  'color' => '#1E8E3E'],
    ['label' => 'Pending',   'value' => $pendingCount,   'pct' => $srPct($pendingCount),   'color' => '#E8710A'],
    ['label' => 'Rejected',  'value' => $rejectedCount,  'pct' => $srPct($rejectedCount),  'color' => '#D93025'],
    ['label' => 'Fulfilled', 'value' => $fulfilledCount, 'pct' => $srPct($fulfilledCount), 'color' => '#8B5CF6'],
];

// ---------------------------------------------------------------------
// Requests by Category (doughnut) — real, but only Medicines/Vaccines
// exist as a real type split; "Medical Supplies"/"Other" are dropped
// (see header comment).
// ---------------------------------------------------------------------
$srMedicineCount = count(array_filter($allSrReports, fn ($r) => ($r['type'] ?? 'medicine') === 'medicine'));
$srVaccineCount = count(array_filter($allSrReports, fn ($r) => ($r['type'] ?? '') === 'vaccine'));
$requestsByCategory = [
    ['label' => 'Medicines', 'value' => $srMedicineCount, 'pct' => $srPct($srMedicineCount), 'color' => '#1A73E8'],
    ['label' => 'Vaccines',  'value' => $srVaccineCount,  'pct' => $srPct($srVaccineCount),  'color' => '#1E8E3E'],
];
$requestsByCategoryTotal = $requestsTotal ?: 1;

// ---------------------------------------------------------------------
// Recent Stock Requests — real, with real pagination
// (includes/shared/pagination_helpers.php) over the filtered set.
// ---------------------------------------------------------------------
$requestStatusMeta = [
    'approved' => ['label' => 'Approved', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'  => ['label' => 'Pending',  'bg' => '#FDECDD', 'color' => '#E8710A'],
    'rejected' => ['label' => 'Rejected', 'bg' => '#FCE4E4', 'color' => '#D93025'],
    'released' => ['label' => 'Released', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
];

$recentRequestsPaged = hc_paginate($allSrReports, 'srrPage', 5);
$recentRequests = [];
foreach ($recentRequestsPaged['items'] as $r) {
    $items = $r['items'] ?? [];
    $requestedAt = $r['requestedAt'] ?? null;
    $recentRequests[] = [
        'id' => 'REQ-' . strtoupper(substr($r['docId'], 0, 8)),
        'requestedBy' => $r['pharmacistName'] ?? 'Unknown',
        'category' => ($r['type'] ?? 'medicine') === 'vaccine' ? 'Vaccines' : 'Medicines',
        'items' => ($items[0]['name'] ?? '(no items)') . (count($items) > 1 ? ' +' . (count($items) - 1) . ' more' : ''),
        'qty' => array_sum(array_column($items, 'qty')),
        'dateRequested' => $requestedAt ? date('M j, Y', strtotime($requestedAt)) : '—',
        'status' => $r['status'] ?? 'pending',
    ];
}
$recentRequestsTotal = $recentRequestsPaged['total'];
$recentRequestsCurrentPage = $recentRequestsPaged['page'];
$recentRequestsTotalPages = $recentRequestsPaged['totalPages'];

// ---------------------------------------------------------------------
// Request Fulfillment Summary — STILL PLACEHOLDER. The real data has
// no partial/unfulfilled distinction (see header comment).
// ---------------------------------------------------------------------
$fulfillmentSummary = [
    ['category' => 'Medicines',        'total' => 78, 'fulfilled' => 50, 'partial' => 12, 'unfulfilled' => 16, 'rate' => 64.1],
    ['category' => 'Vaccines',         'total' => 42, 'fulfilled' => 28, 'partial' => 6,  'unfulfilled' => 8,  'rate' => 66.7],
    ['category' => 'Medical Supplies', 'total' => 17, 'fulfilled' => 8,  'partial' => 5,  'unfulfilled' => 4,  'rate' => 47.1],
    ['category' => 'Other',            'total' => 8,  'fulfilled' => 1,  'partial' => 2,  'unfulfilled' => 5,  'rate' => 12.5],
];
$fulfillmentTotal = [
    'total'       => array_sum(array_column($fulfillmentSummary, 'total')),
    'fulfilled'   => array_sum(array_column($fulfillmentSummary, 'fulfilled')),
    'partial'     => array_sum(array_column($fulfillmentSummary, 'partial')),
    'unfulfilled' => array_sum(array_column($fulfillmentSummary, 'unfulfilled')),
    'rate'        => 59.3,
];
