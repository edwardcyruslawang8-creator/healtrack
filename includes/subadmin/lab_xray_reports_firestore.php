<?php
/**
 * =====================================================================
 * Laboratory & X-ray Reports — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own real
 * assigned facility — the real "lab_results" and "xray_results"
 * collections (hc_get_all_lab_result_docs()/hc_get_all_xray_result_docs(),
 * the same shared readers medical_technician/analytics_reports.php and
 * radiologic_technologist/analytics_reports.php use). Unlike those two
 * pages (one technician role each, facility-wide over every doc), this
 * combines BOTH collections and filters each doc on its own real
 * "facility" field (written from the ordering doctor's facility by
 * api/complete-consultation.php) — no join needed, same as
 * vaccination_records on Vaccine Reports.
 *
 * Every number is a real request COUNT. "Avg. Turnaround" is the real
 * day difference between each completed doc's own orderedDate and
 * completedDate — docs missing either date are left out of the average
 * rather than guessed.
 *
 * The filter toolbar is fully real: date range (real "orderedDate"),
 * Type (Laboratory / X-ray) and Status (Completed / Pending) all filter
 * the WHOLE page.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/pagination_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/predictive_analytics_firestore.php'; // $facility, $facilityName, $facilityPatients — locked to THIS sub-admin's own real facility
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_get_all_lab_result_docs()
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_all_xray_result_docs()

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        default => 'Sub-Administrator',
    },
    'facility' => $facilityName,
];

$analyticsDateRangeLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Analytics tabs — each is its own page (not client-side tab-switching).
// Lab & X-ray Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'lab_xray_reports',      'label' => 'Lab & X-ray Reports',   'href' => 'lab_xray_reports.php', 'active' => true],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

$lxrLabColor = '#7C3AED';
$lxrXrayColor = '#1A73E8';

// ---------------------------------------------------------------------
// This facility's own real lab + X-ray requests, flattened into one
// shape (type/name/category/status/dates) before this page's own
// filters are applied.
// ---------------------------------------------------------------------
$lxrAll = [];
foreach (hc_get_all_lab_result_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') !== $facilityName) {
        continue;
    }
    $lxrAll[] = [
        'id' => $doc['id'],
        'type' => 'lab',
        'patientId' => $f['patientId'] ?? '',
        'name' => trim((string) ($f['testName'] ?? '')),
        'category' => trim((string) ($f['testType'] ?? '')),
        'orderedBy' => $f['orderedBy'] ?? '—',
        'orderedDate' => (string) ($f['orderedDate'] ?? ''),
        'completedDate' => (string) ($f['completedDate'] ?? ''),
        'status' => $f['status'] ?? 'pending',
    ];
}
foreach (hc_get_all_xray_result_docs() as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') !== $facilityName) {
        continue;
    }
    $lxrAll[] = [
        'id' => $doc['id'],
        'type' => 'xray',
        'patientId' => $f['patientId'] ?? '',
        'name' => trim((string) ($f['examName'] ?? '')),
        'category' => trim((string) ($f['bodyPart'] ?? '')),
        'orderedBy' => $f['orderedBy'] ?? '—',
        'orderedDate' => (string) ($f['orderedDate'] ?? ''),
        'completedDate' => (string) ($f['completedDate'] ?? ''),
        'status' => $f['status'] ?? 'pending',
    ];
}

// ---------------------------------------------------------------------
// Filter toolbar.
// ---------------------------------------------------------------------
$lxrTypeFilterOptions = ['All Types', 'Laboratory', 'X-ray'];
$lxrStatusFilterOptions = ['All Status', 'Completed', 'Pending'];
$lxrTypeLabelToKey = ['Laboratory' => 'lab', 'X-ray' => 'xray'];

$lxrDateFilter = hc_resolve_date_range_filter();
$lxrTypeFilterLabel = (string) ($_GET['testType'] ?? '');
$lxrStatusFilterLabel = (string) ($_GET['status'] ?? '');

$lxrRequests = array_values(array_filter($lxrAll, function ($r) use ($lxrDateFilter, $lxrTypeFilterLabel, $lxrStatusFilterLabel, $lxrTypeLabelToKey) {
    if (!hc_date_in_range($r['orderedDate'], $lxrDateFilter)) {
        return false;
    }
    if ($lxrTypeFilterLabel !== '' && $r['type'] !== ($lxrTypeLabelToKey[$lxrTypeFilterLabel] ?? '__none__')) {
        return false;
    }
    if ($lxrStatusFilterLabel !== '' && strcasecmp($r['status'], $lxrStatusFilterLabel) !== 0) {
        return false;
    }
    return true;
}));
usort($lxrRequests, fn ($a, $b) => strcmp($b['orderedDate'], $a['orderedDate']));

$lxrLab = array_values(array_filter($lxrRequests, fn ($r) => $r['type'] === 'lab'));
$lxrXray = array_values(array_filter($lxrRequests, fn ($r) => $r['type'] === 'xray'));
$lxrCompleted = array_values(array_filter($lxrRequests, fn ($r) => $r['status'] === 'completed'));
$lxrPending = array_values(array_filter($lxrRequests, fn ($r) => $r['status'] === 'pending'));
$lxrLabCompleted = count(array_filter($lxrLab, fn ($r) => $r['status'] === 'completed'));
$lxrXrayCompleted = count(array_filter($lxrXray, fn ($r) => $r['status'] === 'completed'));

// ---------------------------------------------------------------------
// Summary stat cards.
// ---------------------------------------------------------------------
$lxrTurnaroundDays = [];
foreach ($lxrCompleted as $r) {
    $ordered = DateTimeImmutable::createFromFormat('Y-m-d', substr($r['orderedDate'], 0, 10));
    $completed = DateTimeImmutable::createFromFormat('Y-m-d', substr($r['completedDate'], 0, 10));
    if ($ordered === false || $completed === false || $completed < $ordered) {
        continue;
    }
    $lxrTurnaroundDays[] = (int) $ordered->diff($completed)->days;
}
$lxrAvgTurnaround = $lxrTurnaroundDays !== [] ? round(array_sum($lxrTurnaroundDays) / count($lxrTurnaroundDays), 1) : null;

$lxrThisMonthPrefix = date('Y-m');
$lxrLastMonthPrefix = date('Y-m', strtotime('first day of last month'));
$lxrThisMonth = count(array_filter($lxrRequests, fn ($r) => str_starts_with($r['orderedDate'], $lxrThisMonthPrefix)));
$lxrLastMonth = count(array_filter($lxrRequests, fn ($r) => str_starts_with($r['orderedDate'], $lxrLastMonthPrefix)));
$lxrGrowthPct = hc_facility_growth_pct($lxrThisMonth, $lxrLastMonth);

$labXrayStatCards = [
    ['icon' => 'bi-clipboard2-data-fill',      'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Requests',          'value' => count($lxrRequests), 'sub' => number_format($lxrThisMonth) . ' this month (' . abs($lxrGrowthPct) . '% vs last)', 'trend' => $lxrGrowthPct < 0 ? 'down' : 'up'],
    ['icon' => 'bi-eyedropper',                'iconBg' => '#EFE7FB', 'iconColor' => $lxrLabColor, 'label' => 'Lab Tests Completed',    'value' => $lxrLabCompleted,   'sub' => number_format(count($lxrLab)) . ' lab request(s) total'],
    ['icon' => 'bi-file-earmark-medical-fill', 'iconBg' => '#E8F0FE', 'iconColor' => $lxrXrayColor, 'label' => 'X-ray Exams Completed', 'value' => $lxrXrayCompleted,  'sub' => number_format(count($lxrXray)) . ' X-ray request(s) total'],
    ['icon' => 'bi-hourglass-split',           'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Pending Results',         'value' => count($lxrPending), 'sub' => 'Ordered, no result yet'],
    ['icon' => 'bi-stopwatch-fill',            'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Avg. Turnaround',         'value' => $lxrAvgTurnaround,  'valueSuffix' => $lxrAvgTurnaround == 1 ? ' day' : ' days', 'sub' => 'Ordered to completed'],
];

// ---------------------------------------------------------------------
// Requests Trend (two-line chart) — real orders per day, last 14 days.
// ---------------------------------------------------------------------
$lxrLabTrend = hc_last_n_days(array_column($lxrLab, 'orderedDate'), 14);
$lxrXrayTrend = hc_last_n_days(array_column($lxrXray, 'orderedDate'), 14);
$labXrayTrend = ['labels' => $lxrLabTrend['labels'], 'lab' => $lxrLabTrend['values'], 'xray' => $lxrXrayTrend['values']];

// ---------------------------------------------------------------------
// Requests by Type (doughnut).
// ---------------------------------------------------------------------
$requestsByType = [];
if (count($lxrLab) > 0) {
    $requestsByType[] = ['label' => 'Laboratory', 'value' => count($lxrLab), 'color' => $lxrLabColor];
}
if (count($lxrXray) > 0) {
    $requestsByType[] = ['label' => 'X-ray', 'value' => count($lxrXray), 'color' => $lxrXrayColor];
}
$requestsByTypeTotal = array_sum(array_column($requestsByType, 'value'));
foreach ($requestsByType as &$slice) {
    $slice['pct'] = $requestsByTypeTotal > 0 ? round($slice['value'] / $requestsByTypeTotal * 100, 1) : 0;
}
unset($slice);

// ---------------------------------------------------------------------
// Status by Type (grouped bar) — completed vs pending per type.
// ---------------------------------------------------------------------
$statusByType = [
    'labels' => ['Laboratory', 'X-ray'],
    'completed' => [$lxrLabCompleted, $lxrXrayCompleted],
    'pending' => [count($lxrLab) - $lxrLabCompleted, count($lxrXray) - $lxrXrayCompleted],
];

// ---------------------------------------------------------------------
// Top 5 ranked lists — Lab Tests, X-ray Exams, Ordering Physicians.
// ---------------------------------------------------------------------
function lxr_top_counts(array $requests, string $key, int $limit = 5): array
{
    $tallies = [];
    foreach ($requests as $r) {
        $label = trim((string) ($r[$key] ?? ''));
        if ($label === '' || $label === '—') {
            continue;
        }
        $tallies[$label] = ($tallies[$label] ?? 0) + 1;
    }
    arsort($tallies);
    $rows = [];
    foreach (array_slice($tallies, 0, $limit, true) as $label => $value) {
        $rows[] = ['label' => $label, 'value' => $value];
    }
    return $rows;
}
$topLabTests = lxr_top_counts($lxrLab, 'name');
$topLabTestsMax = $topLabTests !== [] ? max(array_column($topLabTests, 'value')) : 1;
$topXrayExams = lxr_top_counts($lxrXray, 'name');
$topXrayExamsMax = $topXrayExams !== [] ? max(array_column($topXrayExams, 'value')) : 1;
$topOrderingPhysicians = lxr_top_counts($lxrRequests, 'orderedBy');
$topOrderingPhysiciansMax = $topOrderingPhysicians !== [] ? max(array_column($topOrderingPhysicians, 'value')) : 1;

// ---------------------------------------------------------------------
// Recent Requests — table, real click-to-sort + pagination, patient
// names joined from this facility's own real patients.
// ---------------------------------------------------------------------
$lxrPatientNames = array_column($facilityPatients, 'name', 'id');
$lxrRows = array_map(fn ($r) => [
    'patientId' => $r['patientId'],
    'patientName' => $lxrPatientNames[$r['patientId']] ?? '(unknown patient)',
    'typeLabel' => $r['type'] === 'lab' ? 'Laboratory' : 'X-ray',
    'typeColor' => $r['type'] === 'lab' ? $lxrLabColor : $lxrXrayColor,
    'name' => $r['name'] !== '' ? $r['name'] : '—',
    'orderedBy' => $r['orderedBy'],
    'orderedDate' => $r['orderedDate'] !== '' ? date('M j, Y', strtotime($r['orderedDate'])) : '—',
    'completedDate' => $r['completedDate'] !== '' ? date('M j, Y', strtotime($r['completedDate'])) : '—',
    'status' => $r['status'],
], $lxrRequests);

$lxrStatusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FEF3C7', 'color' => '#B45309'],
];

$lxrPaged = hc_paginate($lxrRows, 'lxrPage', 10);
$recentLabXrayRequests = $lxrPaged['items'];
$recentLabXrayTotal = $lxrPaged['total'];
$recentLabXrayCurrentPage = $lxrPaged['page'];
$recentLabXrayTotalPages = $lxrPaged['totalPages'];
$recentLabXrayPerPage = 10;
