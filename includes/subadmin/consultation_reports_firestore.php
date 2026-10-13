<?php
/**
 * =====================================================================
 * Consultation Reports — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own real
 * assigned facility — the "consultations" collection, joined to this
 * facility's own real patients via patientId (consultations has no
 * "facility" field of its own — same join
 * includes/admin/facility_analytics_firestore.php already uses). This
 * was previously entirely placeholder; every number below is now real.
 *
 * The filter toolbar is fully real now: date range (real "dateTime"
 * field), Consultation Type (real "consultationType",
 * hc_consultation_type_meta()), Status (real "status",
 * hc_consultation_status_meta()) all filter the WHOLE page (stat cards,
 * both donuts, the trend chart, and both tables' underlying data) —
 * Physician and Barangay stay "table-only" filters (each narrows just
 * its own matching table to the selected row, same as before) rather
 * than also narrowing the other table, since picking one physician
 * doesn't mean the Barangay table should hide every other barangay's
 * real total too. Barangay OPTIONS are derived from real distinct
 * values actually seen at this facility, not a fixed list; Physician
 * OPTIONS are the real registered doctor accounts at this facility
 * (hc_get_physician_options()) instead — see that filter's own comment
 * below for why.
 * "Today's Consultations" always reflects literally today regardless of
 * the date-range filter, same as any other "today" pulse stat.
 *
 * Both tables have real click-to-sort columns
 * (assets/js/sortable-table.js) and real pagination
 * (includes/shared/pagination_helpers.php).
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/pagination_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/predictive_analytics_firestore.php'; // $facility, $facilityName, $facilityPatients (unused directly — re-derived below to also get barangay) — locked to THIS sub-admin's own real facility
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_all_consultations(), hc_consultation_type_meta(), hc_consultation_status_meta()

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
// Consultation Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php', 'active' => true],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// This facility's own real patients (id -> barangay map, for the
// Barangay table's join) and real consultations, joined via patientId.
// ---------------------------------------------------------------------
$crFacilityPatientIds = [];
$crPatientBarangayById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') === $facilityName) {
        $crFacilityPatientIds[] = $doc['id'];
    }
    $crPatientBarangayById[$doc['id']] = trim((string) ($f['barangay'] ?? ''));
}
$crFacilityConsultationsAll = array_values(array_filter(
    hc_get_all_consultations(),
    fn ($c) => in_array($c['patientId'], $crFacilityPatientIds, true)
));

// "Today's Consultations" always reflects literally today, independent
// of whatever date-range filter is currently selected.
$todaysConsultationsCount = count(array_filter($crFacilityConsultationsAll, fn ($c) => str_starts_with($c['dateTime'], date('Y-m-d'))));

// ---------------------------------------------------------------------
// Filter toolbar — date range / type / status apply to the WHOLE page;
// options derived from real data actually seen at this facility.
// ---------------------------------------------------------------------
$consultTypeMeta = hc_consultation_type_meta();
$consultStatusMeta = hc_consultation_status_meta();

$consultationTypeFilterOptions = array_merge(['All Consultation Types'], array_column($consultTypeMeta, 'label'));
$statusFilterOptions = array_merge(['All Status'], array_column($consultStatusMeta, 'label'));

$crDateFilter = hc_resolve_date_range_filter();
$crTypeFilterLabel = (string) ($_GET['consultType'] ?? '');
$crStatusFilterLabel = (string) ($_GET['status'] ?? '');

// array_column($meta, 'label') alone would re-index numerically and
// lose the real status/type key each label belongs to — built directly
// off the meta arrays' own keys instead, so e.g. "Completed" maps back
// to the real 'completed' key, not to a throwaway numeric index.
$crTypeLabelToKey = array_combine(array_column($consultTypeMeta, 'label'), array_keys($consultTypeMeta));
$crStatusLabelToKey = array_combine(array_column($consultStatusMeta, 'label'), array_keys($consultStatusMeta));

$facilityConsultations = array_values(array_filter($crFacilityConsultationsAll, function ($c) use ($crDateFilter, $crTypeFilterLabel, $crStatusFilterLabel, $crTypeLabelToKey, $crStatusLabelToKey) {
    if (!hc_date_in_range($c['dateTime'], $crDateFilter)) {
        return false;
    }
    if ($crTypeFilterLabel !== '' && $c['consultationType'] !== ($crTypeLabelToKey[$crTypeFilterLabel] ?? '__none__')) {
        return false;
    }
    if ($crStatusFilterLabel !== '' && $c['status'] !== ($crStatusLabelToKey[$crStatusFilterLabel] ?? '__none__')) {
        return false;
    }
    return true;
}));

$crTotal = count($facilityConsultations);
$crPct = fn (int $n) => $crTotal > 0 ? round($n / $crTotal * 100, 1) : 0;

// ---------------------------------------------------------------------
// Summary stat cards — real.
// ---------------------------------------------------------------------
$completedCount = count(array_filter($facilityConsultations, fn ($c) => $c['status'] === 'completed'));
$pendingCount = count(array_filter($facilityConsultations, fn ($c) => $c['status'] === 'pending'));
$cancelledCount = count(array_filter($facilityConsultations, fn ($c) => $c['status'] === 'cancelled'));

$consultationStatCards = [
    ['icon' => 'bi-people-fill',          'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Consultations',    'value' => $crTotal, 'sub' => 'All registered consultations'],
    ['icon' => 'bi-heart-pulse-fill',     'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => "Today's Consultations",  'value' => $todaysConsultationsCount, 'sub' => date('F j, Y')],
    ['icon' => 'bi-clipboard2-check-fill','iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Completed Consultations','value' => $completedCount, 'sub' => $crPct($completedCount) . '% of total'],
    ['icon' => 'bi-clock-fill',           'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Pending Consultations',  'value' => $pendingCount, 'sub' => $crPct($pendingCount) . '% of total'],
    ['icon' => 'bi-x-circle-fill',        'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Cancelled Consultations','value' => $cancelledCount, 'sub' => $crPct($cancelledCount) . '% of total'],
];

// ---------------------------------------------------------------------
// Consultations by Type (doughnut) — real.
// ---------------------------------------------------------------------
$crTypeTallies = [];
foreach ($facilityConsultations as $c) {
    $crTypeTallies[$c['consultationType']] = ($crTypeTallies[$c['consultationType']] ?? 0) + 1;
}
$consultationsByType = [];
foreach ($consultTypeMeta as $key => $meta) {
    if (($crTypeTallies[$key] ?? 0) > 0) {
        $consultationsByType[] = ['label' => $meta['label'], 'value' => $crTypeTallies[$key], 'pct' => $crPct($crTypeTallies[$key]), 'color' => $meta['color']];
    }
}
$consultationsByTypeTotal = array_sum(array_column($consultationsByType, 'value'));

// ---------------------------------------------------------------------
// Consultations by Status (doughnut) — real.
// ---------------------------------------------------------------------
$crStatusTallies = [];
foreach ($facilityConsultations as $c) {
    $crStatusTallies[$c['status']] = ($crStatusTallies[$c['status']] ?? 0) + 1;
}
$consultationsByStatus = [];
foreach ($consultStatusMeta as $key => $meta) {
    if (($crStatusTallies[$key] ?? 0) > 0) {
        $consultationsByStatus[] = ['label' => $meta['label'], 'value' => $crStatusTallies[$key], 'pct' => $crPct($crStatusTallies[$key]), 'color' => $meta['color']];
    }
}
$consultationsByStatusTotal = array_sum(array_column($consultationsByStatus, 'value'));

// ---------------------------------------------------------------------
// Consultations Trend (line chart) — real, last 14 real days.
// ---------------------------------------------------------------------
$consultationsTrendBucketed = hc_last_n_days(array_column($facilityConsultations, 'dateTime'), 14);
$consultationsTrend = ['labels' => $consultationsTrendBucketed['labels'], 'values' => $consultationsTrendBucketed['values']];

// ---------------------------------------------------------------------
// Consultations by Physician — real, table-only Physician filter
// narrows just this table (doesn't affect stat cards/donuts/trend
// above). Options are the real REGISTERED doctor accounts at this
// facility (hc_get_physician_options() — same source
// doctor/consultations.php's own "Assigned Physician" field uses when
// creating a consultation), not just whichever "assignedPersonnel"
// strings happen to appear on a consultation doc — a name typed in
// before an account existed, or a doctor who's since left/been
// deactivated, would otherwise show up here as a selectable filter
// with no real account behind it.
// ---------------------------------------------------------------------
$crRegisteredPhysicians = array_column(hc_get_physician_options($facilityName), 'name');
$physicianFilterOptions = array_merge(['All Physicians'], $crRegisteredPhysicians);

$crByPhysician = [];
foreach ($facilityConsultations as $c) {
    $name = $c['assignedPersonnel'];
    if ($name === '' || $name === '—') {
        continue;
    }
    $crByPhysician[$name] ??= ['physician' => $name, 'total' => 0, 'completed' => 0, 'pending' => 0, 'cancelled' => 0];
    $crByPhysician[$name]['total']++;
    if (isset($crByPhysician[$name][$c['status']])) {
        $crByPhysician[$name][$c['status']]++;
    }
}
$allPhysicianConsultations = array_values(array_map(fn ($p) => $p + ['rate' => $p['total'] > 0 ? round($p['completed'] / $p['total'] * 100, 1) : 0], $crByPhysician));
usort($allPhysicianConsultations, fn ($a, $b) => $b['total'] <=> $a['total']);

$crPhysicianFilter = (string) ($_GET['physician'] ?? '');
$filteredPhysicianConsultations = $crPhysicianFilter === ''
    ? $allPhysicianConsultations
    : array_values(array_filter($allPhysicianConsultations, fn ($r) => $r['physician'] === $crPhysicianFilter));

$physicianConsultationsPaged = hc_paginate($filteredPhysicianConsultations, 'physPage', 5);
$physicianConsultations = $physicianConsultationsPaged['items'];
$physicianConsultationsTotal = $physicianConsultationsPaged['total'];
$physicianConsultationsCurrentPage = $physicianConsultationsPaged['page'];
$physicianConsultationsTotalPages = $physicianConsultationsPaged['totalPages'];

// ---------------------------------------------------------------------
// Consultations by Barangay — real, joined via patientId -> this
// facility's own real patients' barangay field. Table-only Barangay
// filter, same pattern as Physician above.
// ---------------------------------------------------------------------
$crByBarangay = [];
foreach ($facilityConsultations as $c) {
    $bgy = $crPatientBarangayById[$c['patientId']] ?? '';
    if ($bgy === '') {
        continue;
    }
    $crByBarangay[$bgy] = ($crByBarangay[$bgy] ?? 0) + 1;
}
arsort($crByBarangay);
$crBarangayTotalForPct = array_sum($crByBarangay) ?: 1;
$allConsultationsByBarangay = [];
foreach ($crByBarangay as $bgy => $count) {
    $allConsultationsByBarangay[] = ['barangay' => $bgy, 'total' => $count, 'pct' => round($count / $crBarangayTotalForPct * 100, 1)];
}

$barangayFilterOptions = array_merge(['All Barangays'], array_column($allConsultationsByBarangay, 'barangay'));

$crBarangayFilter = (string) ($_GET['barangay'] ?? '');
$filteredConsultationsByBarangay = $crBarangayFilter === ''
    ? $allConsultationsByBarangay
    : array_values(array_filter($allConsultationsByBarangay, fn ($r) => $r['barangay'] === $crBarangayFilter));

$consultationsByBarangayPaged = hc_paginate($filteredConsultationsByBarangay, 'brgyPage', 5);
$consultationsByBarangay = $consultationsByBarangayPaged['items'];
$consultationsByBarangayTotal = $consultationsByBarangayPaged['total'];
$consultationsByBarangayCurrentPage = $consultationsByBarangayPaged['page'];
$consultationsByBarangayTotalPages = $consultationsByBarangayPaged['totalPages'];
