<?php
/**
 * =====================================================================
 * Analytics & Reports (Sub-Admin, Overview tab) — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data end to end now, scoped to THIS
 * sub-admin's own real assigned facility (never system-wide) — every
 * KPI, chart, and list below is computed from real collections:
 *   - Total/New Patients: "patients" (real "facility" field —
 *     api/create-patient.php).
 *   - Consultations Over Time / Consultations by Type: "consultations",
 *     joined to this facility's own real patients (that collection has
 *     no "facility" field of its own).
 *   - Medicines Dispensed / Top 5 Medicines Dispensed: "pharmacy_orders"
 *     (real "facility" field), status=dispensed — an ORDER-FREQUENCY
 *     proxy (a count of dispensing events per item), not true units,
 *     since no numeric dispensed-quantity field exists anywhere in this
 *     app's schema (same honesty convention
 *     includes/shared/predictive_analytics_core.php's own header
 *     comment documents).
 *   - Stock Requests / Recent Stock Requests: "stock_requests" (real
 *     "facility" field).
 *   - Inventory Status Summary: "medicines" — system-wide, not scoped
 *     per facility (that collection has no per-facility field anywhere
 *     in this app's schema — same honest exception every other
 *     inventory section in this app already notes).
 *   - Top 5 Diagnoses: "medical_records" diagnosis text at this
 *     facility — free text (no coded diagnosis field exists), so this
 *     tallies the real stored strings as-is.
 *
 * Reuses includes/subadmin/predictive_analytics_firestore.php (which
 * itself locks onto this same facility via hc_resolve_own_facility_id()
 * and reuses the real admin computation pipeline) for the facility
 * resolution AND for $facilityPatients/$facilityConsultations/
 * $facilityRecordDocs — already computed once there, not re-derived
 * here — plus the real forecast numbers ($patientFit/$consultFit/...)
 * this page's own small "Predictive Analytics" preview section below
 * reuses directly (see subadmin/analytics_reports.php's own template
 * for that section, which links to predictive_analytics.php for the
 * full picture).
 * =====================================================================
 */

require_once __DIR__ . '/predictive_analytics_firestore.php';
// $subadminFacilityUnresolved, $facility, $facilityName, $facilityPatients (unused here — see below),
// $facilityConsultations, $facilityRecordDocs, $patientFit, $consultFit, $patientForecastTotal,
// $consultForecastTotal, $patientPast7Total, $consultPast7Total, $pt1PredictionCards (6.3.1 KPI cards)
require_once __DIR__ . '/../firestore/consultations.php'; // hc_consultation_type_meta()

// Rebuilt into this page's own real sub-admin $adminProfile convention
// (name/role/facility) — 'facility' is $facilityName itself (already
// resolved above), guaranteed to agree with every number on this page,
// same pattern includes/subadmin/predictive_analytics_firestore.php uses.
$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $facilityName,
];

// Real, up-to-date single date — was hardcoded to a frozen
// "May 1, 2026 - May 20, 2026" range before; every KPI below is now
// real Firestore data, so the label needs to actually track "today"
// instead of a placeholder date nobody updates.
$analyticsDateRangeLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Analytics tabs — each is its own page (not client-side tab-switching).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php', 'active' => true],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'lab_xray_reports',      'label' => 'Lab & X-ray Reports',   'href' => 'lab_xray_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// Real patients at THIS facility only (not system-wide) — same scoping
// every other sub-admin page already uses.
// ---------------------------------------------------------------------
$analyticsPatients = array_values(array_filter(hc_get_all_patients_summary(), fn ($p) => $p['facility'] === $facilityName));
$analyticsThisMonthPrefix = date('Y-m');
$analyticsLastMonthPrefix = date('Y-m', strtotime('first day of last month'));
$analyticsNewPatientsThisMonth = count(array_filter($analyticsPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $analyticsThisMonthPrefix)));
$analyticsNewPatientsLastMonth = count(array_filter($analyticsPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $analyticsLastMonthPrefix)));
$analyticsNewPatientsGrowthPct = hc_facility_growth_pct($analyticsNewPatientsThisMonth, $analyticsNewPatientsLastMonth);

// Real stock requests at THIS facility (real "facility" field), newest first.
$rawAnalyticsRequests = array_values(array_filter(
    $firestore->listDocuments('stock_requests'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));
usort($rawAnalyticsRequests, fn ($a, $b) => strcmp($b['fields']['requestedAt'] ?? '', $a['fields']['requestedAt'] ?? ''));

// Real "operational issue" comparative/anomaly view
// (hc_operational_trends_overview()) over this facility's own stock
// requests — same mean/standard-deviation anomaly method the disease/
// consultation-volume checks already use, applied to real request
// volume + real processing time (request -> decision). Reuses
// $facilityStockRequests (already resolved to THIS same facility by
// the predictive_analytics_firestore.php require chain above) rather
// than reshaping $rawAnalyticsRequests' own raw-doc shape, since
// hc_operational_trends_overview() needs the requestedAtRaw/
// reviewedAtRaw fields hc_get_all_stock_requests() already provides.
$operationalTrends = hc_operational_trends_overview($facilityStockRequests);

// Real dispensed pharmacy orders at THIS facility (real "facility" field).
$analyticsDispensedOrders = array_values(array_filter(
    $firestore->listDocuments('pharmacy_orders'),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName && ($doc['fields']['status'] ?? '') === 'dispensed'
));
$analyticsDispensedThisMonth = count(array_filter($analyticsDispensedOrders, fn ($doc) => str_starts_with($doc['fields']['dispensedDate'] ?? '', $analyticsThisMonthPrefix)));
$analyticsDispensedLastMonth = count(array_filter($analyticsDispensedOrders, fn ($doc) => str_starts_with($doc['fields']['dispensedDate'] ?? '', $analyticsLastMonthPrefix)));
$analyticsDispensedGrowthPct = hc_facility_growth_pct($analyticsDispensedThisMonth, $analyticsDispensedLastMonth);

$analyticsRequestsThisMonth = count(array_filter($rawAnalyticsRequests, fn ($doc) => str_starts_with($doc['fields']['requestedAt'] ?? '', $analyticsThisMonthPrefix)));
$analyticsRequestsLastMonth = count(array_filter($rawAnalyticsRequests, fn ($doc) => str_starts_with($doc['fields']['requestedAt'] ?? '', $analyticsLastMonthPrefix)));
$analyticsRequestsGrowthPct = hc_facility_growth_pct($analyticsRequestsThisMonth, $analyticsRequestsLastMonth);

// ---------------------------------------------------------------------
// KPI summary cards — all 5 real now.
// ---------------------------------------------------------------------
// Real "vs last month" % on each card that has a natural monthly
// comparison (hc_facility_growth_pct(), same helper admin's own
// Facility Analytics page uses — includes/shared/analytics_comparison.php)
// instead of the previous hardcoded 'trend' => 'up' on every card
// regardless of what actually happened.
$kpiCards = [
    ['icon' => 'bi-people-fill',            'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Patients',                    'value' => count($analyticsPatients), 'sub' => 'All registered patients', 'trend' => 'up'],
    ['icon' => 'bi-person-plus-fill',       'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'New Patients (This Month)',         'value' => $analyticsNewPatientsThisMonth, 'sub' => abs($analyticsNewPatientsGrowthPct) . '% vs last month', 'trend' => $analyticsNewPatientsGrowthPct < 0 ? 'down' : 'up'],
    ['icon' => 'bi-clipboard2-pulse-fill',  'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Patients Registered (This Month)',  'value' => $analyticsNewPatientsThisMonth, 'sub' => abs($analyticsNewPatientsGrowthPct) . '% vs last month', 'trend' => $analyticsNewPatientsGrowthPct < 0 ? 'down' : 'up'],
    ['icon' => 'bi-capsule',                'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Medicines Dispensed',               'value' => count($analyticsDispensedOrders), 'sub' => abs($analyticsDispensedGrowthPct) . '% vs last month', 'trend' => $analyticsDispensedGrowthPct < 0 ? 'down' : 'up'],
    ['icon' => 'bi-cart-fill',              'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Stock Requests',                    'value' => count($rawAnalyticsRequests), 'sub' => abs($analyticsRequestsGrowthPct) . '% vs last month', 'trend' => $analyticsRequestsGrowthPct < 0 ? 'down' : 'up'],
];

// ---------------------------------------------------------------------
// Consultations Over Time (line chart) — real, this facility's own
// consultations ($facilityConsultations, already computed by the
// required predictive pipeline), bucketed by the card's own
// Daily/Weekly/Monthly range dropdown via hc_facility_trend_by_range()
// (includes/admin/facility_analytics_firestore.php — the same
// DateTime/DateInterval bucketing admin's own Patient Trend card uses,
// already loaded transitively by the require above) — same
// smooth-AJAX-refresh treatment as that card (api/get-consultation-trend.php
// + assets/js/analytics_reports.js).
// ---------------------------------------------------------------------
$consultTrendRangeOptions = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$consultTrendRange = $_GET['consultRange'] ?? 'daily';
if (!array_key_exists($consultTrendRange, $consultTrendRangeOptions)) {
    $consultTrendRange = 'daily';
}
$consultationsOverTimeBucketed = hc_facility_trend_by_range(array_column($facilityConsultations, 'dateTime'), $consultTrendRange);
$consultationsOverTimeLabels = $consultationsOverTimeBucketed['labels'];
$consultationsOverTimeValues = $consultationsOverTimeBucketed['values'];

// ---------------------------------------------------------------------
// Consultations by Type (doughnut) — real, tallied from
// $facilityConsultations' own real "consultationType" field.
// ---------------------------------------------------------------------
$consultTypeMeta = hc_consultation_type_meta();
$consultTypeTallies = [];
foreach ($facilityConsultations as $c) {
    $consultTypeTallies[$c['consultationType']] = ($consultTypeTallies[$c['consultationType']] ?? 0) + 1;
}
arsort($consultTypeTallies);
$consultationsByTypeRawTotal = array_sum($consultTypeTallies) ?: 1;
$consultationsByType = [];
foreach ($consultTypeTallies as $type => $count) {
    $meta = $consultTypeMeta[$type] ?? ['label' => ucfirst($type), 'color' => '#6B7280'];
    $consultationsByType[] = ['label' => $meta['label'], 'value' => $count, 'pct' => round($count / $consultationsByTypeRawTotal * 100, 1), 'color' => $meta['color']];
}
$consultationsByTypeTotal = array_sum(array_column($consultationsByType, 'value'));

// ---------------------------------------------------------------------
// Top 5 Medicines Dispensed (horizontal ranked bars) — real, tallied by
// real dispensing-event COUNT per item name at this facility (see this
// file's header comment for why this is a frequency proxy, not units).
// ---------------------------------------------------------------------
$medicineDispenseTallies = [];
foreach ($analyticsDispensedOrders as $doc) {
    $name = trim((string) ($doc['fields']['itemName'] ?? ''));
    if ($name === '') {
        continue;
    }
    $medicineDispenseTallies[$name] = ($medicineDispenseTallies[$name] ?? 0) + 1;
}
arsort($medicineDispenseTallies);
$topMedicinesDispensed = [];
foreach (array_slice($medicineDispenseTallies, 0, 5, true) as $name => $count) {
    $topMedicinesDispensed[] = ['label' => $name, 'value' => $count];
}
$topMedicinesDispensedMax = $topMedicinesDispensed === [] ? 1 : max(array_column($topMedicinesDispensed, 'value'));

// ---------------------------------------------------------------------
// Inventory Status Summary (doughnut) — real, from the "medicines"
// collection's status field. System-wide, not scoped per facility —
// that collection has no per-facility field anywhere in this app's
// schema (same honest exception every other inventory section in this
// app already notes).
// ---------------------------------------------------------------------
// 'overstocked' included alongside the original 3 — a fixed 3-key
// allowlist here would silently drop any overstocked item from this
// whole summary instead of just omitting an empty slice.
$analyticsMedicineStatusCounts = ['available' => 0, 'low' => 0, 'unavailable' => 0, 'overstocked' => 0];
foreach ($firestore->listDocuments('medicines') as $doc) {
    $status = $doc['fields']['status'] ?? 'available';
    if (isset($analyticsMedicineStatusCounts[$status])) {
        $analyticsMedicineStatusCounts[$status]++;
    }
}
$inventoryStatusSummary = [
    ['label' => 'In Stock',     'value' => $analyticsMedicineStatusCounts['available'],   'color' => '#1E8E3E'],
    ['label' => 'Low Stock',    'value' => $analyticsMedicineStatusCounts['low'],         'color' => '#E8710A'],
    ['label' => 'Out of Stock', 'value' => $analyticsMedicineStatusCounts['unavailable'], 'color' => '#D93025'],
    ['label' => 'Overstocked',  'value' => $analyticsMedicineStatusCounts['overstocked'], 'color' => '#8B5CF6'],
];
$inventoryStatusTotal = array_sum(array_column($inventoryStatusSummary, 'value')) ?: 1;

// ---------------------------------------------------------------------
// Recent Stock Requests — real, latest 5 at this facility.
// ---------------------------------------------------------------------
$requestStatusMeta = [
    'pending'  => ['label' => 'Pending',  'bg' => '#FDECDD', 'color' => '#E8710A'],
    'approved' => ['label' => 'Approved', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'released' => ['label' => 'Released', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'rejected' => ['label' => 'Rejected', 'bg' => '#FCE4E4', 'color' => '#D93025'],
];

$recentStockRequests = [];
foreach (array_slice($rawAnalyticsRequests, 0, 5) as $doc) {
    $fields = $doc['fields'];
    $requestedAt = $fields['requestedAt'] ?? null;
    $recentStockRequests[] = [
        'id' => 'REQ-' . strtoupper(substr($doc['id'], 0, 8)),
        'pharmacist' => $fields['pharmacistName'] ?? 'Unknown',
        'requestDate' => $requestedAt ? date('M j, Y', strtotime($requestedAt)) : '—',
        'requestTime' => $requestedAt ? date('g:i A', strtotime($requestedAt)) : '—',
        'itemsCount' => (int) ($fields['itemsCount'] ?? 0),
        'status' => $fields['status'] ?? 'pending',
    ];
}

// ---------------------------------------------------------------------
// Top 5 Diagnoses (horizontal ranked bars) — real, tallied from this
// facility's own real medical_records diagnosis text (free text — no
// coded diagnosis field exists anywhere in this schema, same as
// includes/shared/predictive_analytics_core.php's own disease-detection
// logic notes).
// ---------------------------------------------------------------------
$diagnosisTallies = [];
foreach ($facilityRecordDocs as $doc) {
    $diagnosis = trim((string) ($doc['fields']['diagnosis'] ?? ''));
    if ($diagnosis === '') {
        continue;
    }
    $diagnosisTallies[$diagnosis] = ($diagnosisTallies[$diagnosis] ?? 0) + 1;
}
arsort($diagnosisTallies);
$topDiagnoses = [];
foreach (array_slice($diagnosisTallies, 0, 5, true) as $label => $count) {
    $topDiagnoses[] = ['label' => $label, 'value' => $count];
}
$topDiagnosesMax = $topDiagnoses === [] ? 1 : max(array_column($topDiagnoses, 'value'));