<?php
/**
 * =====================================================================
 * Patient Reports — Data
 * ---------------------------------------------------------------------
 * The "Patient Reports" tab of the Analytics & Reports section — a
 * sibling page to subadmin/analytics_reports.php ("Overview"), sharing
 * its $adminProfile/$facilityOptions/$analyticsTabs shape (see
 * includes/analytics_reports_firestore.php's header comment for why that
 * shape is duplicated per-page rather than shared).
 *
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own real
 * assigned facility (never system-wide) — reuses
 * includes/subadmin/predictive_analytics_firestore.php (which locks onto
 * this facility via hc_resolve_own_facility_id()) purely for
 * $facility/$facilityName, same pattern
 * includes/subadmin/analytics_reports_firestore.php already uses. Every
 * stat card, the Patients by Age Group donut, the Patients by Gender
 * donut, the Patients by Barangay ranked list, and the New Patient
 * Registrations table are read from the real "patients" collection,
 * filtered to this facility's own real "facility" field
 * (api/create-patient.php) — this was previously reading ALL facilities'
 * patients system-wide, a scoping gap fixed here to match the rest of
 * this app's subadmin pages.
 *
 * The filter toolbar (date range on "dateRegistered" / age group /
 * gender / barangay) is now real — see includes/shared/date_range_filter.php
 * and includes/shared/simple_filter_dropdown.php — filtering is applied
 * BEFORE every stat/chart/list below is computed, so a filter selection
 * changes the whole page consistently. New Patient Registrations now has
 * real pagination too (includes/shared/pagination_helpers.php) instead
 * of always showing just the first 5 regardless of which page-btn was
 * clicked.
 *
 * Top 5 Diagnoses is CONNECTED now too — real free-text "diagnosis"
 * field on this facility's own "medical_records" docs (no coded
 * diagnosis field exists anywhere in this app's schema, so real stored
 * strings are tallied as-is — same honest approach
 * includes/subadmin/analytics_reports_firestore.php's own Top 5
 * Diagnoses already uses), joined to only the currently-filtered
 * patients ($allReportPatients below) so this list respects the same
 * date/age/gender/barangay filters as every other section on this page.
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/pagination_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/predictive_analytics_firestore.php'; // $facility, $facilityName — locked to THIS sub-admin's own real facility
// (also pulls in the full admin predictive pipeline transitively, unused
// here beyond $facilityName — same "reuse the real facility resolution,
// don't re-derive it" convention every sibling report page follows)

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
// Patient Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php', 'active' => true],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php'],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// Real patients at THIS facility only, then this page's own real
// filters applied on top (date range on dateRegistered / age group /
// gender / barangay) — used by every block below.
// ---------------------------------------------------------------------
function patient_reports_age_bucket(int $age): string
{
    return match (true) {
        $age <= 17 => '0 - 17 years',
        $age <= 39 => '18 - 39 years',
        $age <= 59 => '40 - 59 years',
        default => '60 years and above',
    };
}

$facilityReportPatients = array_values(array_filter(
    array_map(fn ($doc) => $doc['fields'], $firestore->listDocuments('patients')),
    fn ($p) => ($p['facility'] ?? '') === $facilityName
));

$prDateFilter = hc_resolve_date_range_filter();
$prAgeGroupFilter = (string) ($_GET['ageGroup'] ?? '');
$prGenderFilter = (string) ($_GET['gender'] ?? '');
$prBarangayFilter = (string) ($_GET['barangay'] ?? '');

$allReportPatients = array_values(array_filter($facilityReportPatients, function ($p) use ($prDateFilter, $prAgeGroupFilter, $prGenderFilter, $prBarangayFilter) {
    if (!hc_date_in_range($p['dateRegistered'] ?? null, $prDateFilter)) {
        return false;
    }
    if ($prAgeGroupFilter !== '' && patient_reports_age_bucket((int) ($p['age'] ?? 0)) !== $prAgeGroupFilter) {
        return false;
    }
    if ($prGenderFilter !== '' && ($p['gender'] ?? '') !== $prGenderFilter) {
        return false;
    }
    if ($prBarangayFilter !== '' && ($p['barangay'] ?? '') !== $prBarangayFilter) {
        return false;
    }
    return true;
}));

$reportPatientsTotal = count($allReportPatients);
$prPct = fn (int $n) => $reportPatientsTotal > 0 ? round($n / $reportPatientsTotal * 100, 1) : 0;

$thisMonthPrefix = date('Y-m');
$newPatientsThisMonth = count(array_filter($allReportPatients, fn ($p) => str_starts_with($p['dateRegistered'] ?? '', $thisMonthPrefix)));
$femalePatientsCount = count(array_filter($allReportPatients, fn ($p) => ($p['gender'] ?? '') === 'Female'));
$malePatientsCount = count(array_filter($allReportPatients, fn ($p) => ($p['gender'] ?? '') === 'Male'));
$pediatricCount = count(array_filter($allReportPatients, fn ($p) => (int) ($p['age'] ?? 0) < 18));
$elderlyCount = count(array_filter($allReportPatients, fn ($p) => (int) ($p['age'] ?? 0) >= 60));

// Barangay filter option list is derived from every patient at this
// facility (not just the currently-filtered subset), so picking a
// barangay never removes other barangays from the dropdown itself.
$seenBarangays = array_unique(array_filter(array_column($facilityReportPatients, 'barangay')));
sort($seenBarangays);

// ---------------------------------------------------------------------
// Filter toolbar dropdown options — all 4 (date range / age group /
// gender / barangay) are real now; the barangay list is derived from
// $facilityReportPatients above.
// ---------------------------------------------------------------------
$ageGroupFilterOptions = ['All Age Groups', '0 - 17 years', '18 - 39 years', '40 - 59 years', '60 years and above'];
$genderFilterOptions = ['All Gender', 'Female', 'Male'];
$barangayFilterOptions = array_merge(['All Barangays'], $seenBarangays);

// ---------------------------------------------------------------------
// Summary stat cards — real.
// ---------------------------------------------------------------------
$patientStatCards = [
    ['icon' => 'bi-people-fill',        'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Patients',               'value' => $reportPatientsTotal, 'sub' => 'All registered patients'],
    ['icon' => 'bi-person-plus-fill',   'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'New Patients (This Period)',   'value' => $newPatientsThisMonth, 'sub' => date('F Y')],
    ['icon' => 'bi-gender-female',      'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Female Patients',              'value' => $femalePatientsCount, 'sub' => $prPct($femalePatientsCount) . '% of total'],
    ['icon' => 'bi-gender-male',        'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Male Patients',                'value' => $malePatientsCount,   'sub' => $prPct($malePatientsCount) . '% of total'],
    ['icon' => 'bi-people-fill',        'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Pediatric Patients (<18 yrs)', 'value' => $pediatricCount,      'sub' => $prPct($pediatricCount) . '% of total'],
    ['icon' => 'bi-person-hearts',      'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Elderly Patients (≥60 yrs)',   'value' => $elderlyCount,        'sub' => $prPct($elderlyCount) . '% of total'],
];

// ---------------------------------------------------------------------
// Patients by Age Group (doughnut) — real.
// ---------------------------------------------------------------------
$ageBucketOrder = ['0 - 17 years', '18 - 39 years', '40 - 59 years', '60 years and above'];
$ageBucketColors = ['0 - 17 years' => '#3B82F6', '18 - 39 years' => '#1E8E3E', '40 - 59 years' => '#E8710A', '60 years and above' => '#D93025'];
$ageBucketCounts = array_fill_keys($ageBucketOrder, 0);
foreach ($allReportPatients as $p) {
    $ageBucketCounts[patient_reports_age_bucket((int) ($p['age'] ?? 0))]++;
}
$patientsByAgeGroup = array_map(fn ($label) => [
    'label' => $label,
    'value' => $ageBucketCounts[$label],
    'pct' => $prPct($ageBucketCounts[$label]),
    'color' => $ageBucketColors[$label],
], $ageBucketOrder);
$patientsByAgeGroupTotal = $reportPatientsTotal ?: 1;

// ---------------------------------------------------------------------
// Patients by Gender (doughnut) — real.
// ---------------------------------------------------------------------
$patientsByGenderReport = [
    ['label' => 'Female', 'value' => $femalePatientsCount, 'pct' => $prPct($femalePatientsCount), 'color' => '#8B5CF6'],
    ['label' => 'Male',   'value' => $malePatientsCount,   'pct' => $prPct($malePatientsCount),   'color' => '#1A73E8'],
];
$patientsByGenderReportTotal = $reportPatientsTotal ?: 1;

// ---------------------------------------------------------------------
// Patients by Barangay (Top 5) — real, horizontal ranked-bar list, same
// widget as Overview's "Top 5 Medicines Dispensed" / "Top 5 Diagnoses".
// ---------------------------------------------------------------------
$barangayCounts = [];
foreach ($allReportPatients as $p) {
    $b = $p['barangay'] ?? '';
    if ($b === '') {
        continue;
    }
    $barangayCounts[$b] = ($barangayCounts[$b] ?? 0) + 1;
}
arsort($barangayCounts);
$patientsByBarangay = [];
foreach (array_slice($barangayCounts, 0, 5, true) as $label => $value) {
    $patientsByBarangay[] = ['label' => $label, 'value' => $value];
}
$patientsByBarangayMax = max(array_column($patientsByBarangay, 'value') ?: [1]);

// ---------------------------------------------------------------------
// New Patient Registrations — real, newest first, with real pagination
// (includes/shared/pagination_helpers.php) over the filtered set.
// ---------------------------------------------------------------------
$sortedByRegistration = $allReportPatients;
usort($sortedByRegistration, fn ($a, $b) => strcmp($b['dateRegistered'] ?? '', $a['dateRegistered'] ?? ''));

$registrationsPaged = hc_paginate($sortedByRegistration, 'regPage', 5);
$newPatientRegistrations = [];
foreach ($registrationsPaged['items'] as $p) {
    $newPatientRegistrations[] = [
        'id' => 'PAT-' . strtoupper(substr(md5($p['name'] . $p['dateRegistered']), 0, 8)),
        'name' => $p['name'] ?? '(no name)',
        'age' => (int) ($p['age'] ?? 0),
        'gender' => $p['gender'] ?? '—',
        'barangay' => $p['barangay'] ?? '—',
        'dateRegistered' => isset($p['dateRegistered']) ? date('M j, Y', strtotime($p['dateRegistered'])) : '—',
    ];
}
$registrationsTotal = $registrationsPaged['total'];
$registrationsCurrentPage = $registrationsPaged['page'];
$registrationsTotalPages = $registrationsPaged['totalPages'];

// ---------------------------------------------------------------------
// Top 5 Diagnoses (All Patients) — real, free-text "diagnosis" field on
// this facility's own medical_records docs, restricted to the
// currently-filtered patients above (see header comment).
// ---------------------------------------------------------------------
// $allReportPatients rows come straight from listDocuments()['fields'],
// which has no doc id on it — re-derive the filtered id set by
// re-reading the same real "patients" collection once more, keyed by
// doc id this time, re-applying the exact same real filters above.
$prFilteredPatientIds = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') !== $facilityName) {
        continue;
    }
    if (!hc_date_in_range($f['dateRegistered'] ?? null, $prDateFilter)) {
        continue;
    }
    if ($prAgeGroupFilter !== '' && patient_reports_age_bucket((int) ($f['age'] ?? 0)) !== $prAgeGroupFilter) {
        continue;
    }
    if ($prGenderFilter !== '' && ($f['gender'] ?? '') !== $prGenderFilter) {
        continue;
    }
    if ($prBarangayFilter !== '' && ($f['barangay'] ?? '') !== $prBarangayFilter) {
        continue;
    }
    $prFilteredPatientIds[] = $doc['id'];
}

$prDiagnosisTallies = [];
foreach ($firestore->listDocuments('medical_records') as $doc) {
    $f = $doc['fields'];
    if (($f['facility'] ?? '') !== $facilityName || !in_array($f['patientId'] ?? '', $prFilteredPatientIds, true)) {
        continue;
    }
    $diagnosis = trim((string) ($f['diagnosis'] ?? ''));
    if ($diagnosis === '') {
        continue;
    }
    $prDiagnosisTallies[$diagnosis] = ($prDiagnosisTallies[$diagnosis] ?? 0) + 1;
}
arsort($prDiagnosisTallies);
$topDiagnosesAllTotal = array_sum($prDiagnosisTallies) ?: 1;
$topDiagnosesAll = [];
foreach (array_slice($prDiagnosisTallies, 0, 5, true) as $label => $value) {
    $topDiagnosesAll[] = ['label' => $label, 'value' => $value, 'pct' => round($value / $topDiagnosesAllTotal * 100, 1)];
}
$topDiagnosesAllMax = $topDiagnosesAll === [] ? 1 : max(array_column($topDiagnosesAll, 'value'));
