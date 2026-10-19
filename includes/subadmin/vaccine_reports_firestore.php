<?php
/**
 * =====================================================================
 * Vaccine Reports — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data, scoped to THIS sub-admin's own real
 * assigned facility — the real "vaccination_records" collection (real
 * per-doc "facility" field, no join needed, unlike consultations). This
 * was previously entirely placeholder; every number below is now real.
 *
 * A few numbers deliberately DON'T reuse the original placeholder's
 * shape, because that shape assumed data this collection doesn't carry:
 *   - "Fully/Partially Vaccinated" needs a defined immunization-series
 *     completeness rule per vaccine type (how many doses = "full"?) and
 *     a target population — neither exists here. What DOES exist per
 *     real record is a "status" (completed/pending) and a free-text
 *     "dose" field (e.g. "1 dose", "3rd dose", "Booster") — real doses
 *     ARE counted, and a dose is counted as a real "booster" only when
 *     that free text literally says so, never inferred.
 *   - "Vaccination Coverage (%)" needs a target population that doesn't
 *     exist either. Every chart/table below shows real dose COUNTS
 *     instead, plus a real "Completion Rate" (completed / total real
 *     doses at that grouping) where a rate is shown at all — an honest
 *     number the data actually supports, not a fabricated coverage %.
 * The Vaccine Administration Summary / Vaccination by Barangay tables'
 * own $doses/$fully/$partial/$booster/$coverage keys are kept (renamed
 * in the template's column headers, not the PHP) so the underlying
 * table/pagination/sort/filter plumbing didn't need to change shape —
 * see subadmin/vaccine_reports.php's own header comment for exactly
 * what each column now means.
 *
 * The filter toolbar is fully real now: date range (real
 * "dateAdministered"), Vaccine Type (real "type" field, options derived
 * from real distinct values actually seen — not a fixed list, since
 * this app's real vaccine types aren't a fixed catalog), and Status
 * (real "status": Completed/Pending, replacing the old "Fully/Partially/
 * Not Vaccinated" — that needs the same undefined completeness model
 * noted above, so it's replaced with the one real per-dose status that
 * exists) all filter the WHOLE page. Barangay stays a "table-only"
 * filter (narrows just the Vaccination by Barangay table), same
 * convention as Consultation Reports' Physician/Barangay filters. Age
 * Group isn't a toolbar filter here (it drives its own chart instead —
 * see "Doses Administered by Age Group" below).
 * =====================================================================
 */

require_once __DIR__ . '/../shared/query_helpers.php';
require_once __DIR__ . '/../shared/pagination_helpers.php';
require_once __DIR__ . '/../shared/date_range_filter.php';
require_once __DIR__ . '/predictive_analytics_firestore.php'; // $facility, $facilityName — locked to THIS sub-admin's own real facility
require_once __DIR__ . '/../firestore/vaccination_records.php'; // hc_get_all_vaccination_record_docs()

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
// Vaccine Reports stays active (this IS that page).
// ---------------------------------------------------------------------
$analyticsTabs = [
    ['key' => 'overview',              'label' => 'Overview',              'href' => 'analytics_reports.php'],
    ['key' => 'patient_reports',       'label' => 'Patient Reports',       'href' => 'patient_reports.php'],
    ['key' => 'inventory_reports',     'label' => 'Inventory Reports',     'href' => 'inventory_reports.php'],
    ['key' => 'stock_request_reports', 'label' => 'Stock Request Reports', 'href' => 'stock_request_reports.php'],
    ['key' => 'consultation_reports',  'label' => 'Consultation Reports',  'href' => 'consultation_reports.php'],
    ['key' => 'vaccine_reports',       'label' => 'Vaccine Reports',       'href' => 'vaccine_reports.php', 'active' => true],
    ['key' => 'predictive_analytics',  'label' => 'Predictive & Prescriptive', 'href' => 'predictive_analytics.php'],
];

// ---------------------------------------------------------------------
// Real vaccine "type" -> label/color meta. Not a fixed enum in this
// app's schema (any string is allowed at data-entry), so unknown types
// fall back to a humanized version of the raw key rather than being
// dropped.
// ---------------------------------------------------------------------
$vrTypeMeta = [
    'covid19'      => ['label' => 'COVID-19',             'color' => '#1A73E8'],
    'influenza'    => ['label' => 'Influenza',             'color' => '#1E8E3E'],
    'pneumococcal' => ['label' => 'Pneumococcal',          'color' => '#E8710A'],
    'hepatitis_b'  => ['label' => 'Hepatitis B',           'color' => '#8B5CF6'],
    'tetanus'      => ['label' => 'Tetanus Toxoid (TT)',   'color' => '#12A6C6'],
    'measles'      => ['label' => 'Measles',               'color' => '#D93025'],
    'bcg'          => ['label' => 'BCG',                   'color' => '#B45309'],
    'polio'        => ['label' => 'Polio (OPV/IPV)',       'color' => '#0E9CA8'],
    // Also selectable in the medical-staff "Add Vaccination Record" form
    // (includes/firestore/vaccination_records.php's hc_vaccine_type_meta()).
    'tetanus_diphtheria' => ['label' => 'Tetanus-Diphtheria (Td)', 'color' => '#1558B0'],
    'pentavalent'  => ['label' => 'Pentavalent (DPT-HepB-Hib)', 'color' => '#6366F1'],
    'hpv'          => ['label' => 'HPV',                   'color' => '#DB2777'],
    'rabies'       => ['label' => 'Rabies',                'color' => '#8B5E34'],
    'other'        => ['label' => 'Other',                 'color' => '#9CA3AF'],
];
$vrFallbackColors = ['#9CA3AF', '#6366F1', '#EC4899', '#14B8A6'];
function vr_type_label(string $key, array $meta): string
{
    return $meta[$key]['label'] ?? ucwords(str_replace('_', ' ', $key));
}
function vr_type_color(string $key, array $meta, int $fallbackIndex, array $fallbackColors): string
{
    return $meta[$key]['color'] ?? $fallbackColors[$fallbackIndex % count($fallbackColors)];
}

// ---------------------------------------------------------------------
// This facility's own real vaccination records + patients (for age/
// barangay lookups), before this page's own filters are applied.
// ---------------------------------------------------------------------
$vrFacilityRecordsAll = array_values(array_filter(
    hc_get_all_vaccination_record_docs(),
    fn ($doc) => ($doc['fields']['facility'] ?? '') === $facilityName
));
$vrPatientById = [];
foreach ($firestore->listDocuments('patients') as $doc) {
    $vrPatientById[$doc['id']] = $doc['fields'];
}

// ---------------------------------------------------------------------
// Filter toolbar — options derived from real distinct values actually
// seen at this facility (not a fixed list).
// ---------------------------------------------------------------------
$vrSeenTypes = array_values(array_unique(array_map(fn ($doc) => $doc['fields']['type'] ?? '', $vrFacilityRecordsAll)));
$vrSeenTypes = array_values(array_filter($vrSeenTypes, fn ($t) => $t !== ''));
sort($vrSeenTypes);
$vaccineTypeFilterOptions = array_merge(['All Vaccine Type'], array_map(fn ($t) => vr_type_label($t, $vrTypeMeta), $vrSeenTypes));
$vrTypeLabelToKey = array_combine(array_map(fn ($t) => vr_type_label($t, $vrTypeMeta), $vrSeenTypes), $vrSeenTypes);

$statusFilterOptions = ['All Status', 'Completed', 'Pending'];

$vrDateFilter = hc_resolve_date_range_filter();
$vrTypeFilterLabel = (string) ($_GET['vaccineType'] ?? '');
$vrStatusFilterLabel = (string) ($_GET['status'] ?? '');

$vrFacilityRecords = array_values(array_filter($vrFacilityRecordsAll, function ($doc) use ($vrDateFilter, $vrTypeFilterLabel, $vrStatusFilterLabel, $vrTypeLabelToKey) {
    $f = $doc['fields'];
    if (!hc_date_in_range($f['dateAdministered'] ?? null, $vrDateFilter)) {
        return false;
    }
    if ($vrTypeFilterLabel !== '' && ($f['type'] ?? '') !== ($vrTypeLabelToKey[$vrTypeFilterLabel] ?? '__none__')) {
        return false;
    }
    if ($vrStatusFilterLabel !== '' && strcasecmp($f['status'] ?? '', $vrStatusFilterLabel) !== 0) {
        return false;
    }
    return true;
}));

$vrCompleted = array_values(array_filter($vrFacilityRecords, fn ($doc) => ($doc['fields']['status'] ?? '') === 'completed'));
$vrPending = array_values(array_filter($vrFacilityRecords, fn ($doc) => ($doc['fields']['status'] ?? '') === 'pending'));
$vrBoosters = array_values(array_filter($vrCompleted, fn ($doc) => stripos($doc['fields']['dose'] ?? '', 'boost') !== false));

// ---------------------------------------------------------------------
// Summary stat cards — real (see header comment for why these replace
// the original "fully/partially vaccinated" cards).
// ---------------------------------------------------------------------
$vrUniquePatients = count(array_unique(array_column(array_map(fn ($d) => $d['fields'], $vrCompleted), 'patientId')));
$vrDistinctTypes = count(array_unique(array_map(fn ($d) => $d['fields']['type'] ?? '', $vrCompleted)));
$vrThisMonthPrefix = date('Y-m');
$vrDosesThisMonth = count(array_filter($vrCompleted, fn ($d) => str_starts_with($d['fields']['dateAdministered'] ?? '', $vrThisMonthPrefix)));

$vaccineStatCards = [
    ['icon' => 'bi-droplet-half',       'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Total Doses Administered',   'value' => count($vrCompleted), 'valueSuffix' => '', 'sub' => 'All-time, this facility'], // "bi-syringe" isn't a real Bootstrap Icons 1.11.3 class
    ['icon' => 'bi-hourglass-split',   'iconBg' => '#FEF3C7', 'iconColor' => '#B45309', 'label' => 'Doses Pending',              'value' => count($vrPending),   'valueSuffix' => '', 'sub' => 'Scheduled, not yet given'],
    ['icon' => 'bi-people-fill',       'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E', 'label' => 'Unique Patients Vaccinated', 'value' => $vrUniquePatients,   'valueSuffix' => '', 'sub' => 'At least 1 completed dose'],
    ['icon' => 'bi-capsule',           'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Vaccine Types Administered', 'value' => $vrDistinctTypes,    'valueSuffix' => '', 'sub' => 'Distinct real types on record'],
    ['icon' => 'bi-calendar2-check-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Doses This Month',        'value' => $vrDosesThisMonth,   'valueSuffix' => '', 'sub' => date('F Y')],
];

// ---------------------------------------------------------------------
// Doses Administered by Vaccine Type (doughnut) — real, completed doses
// only.
// ---------------------------------------------------------------------
$vrTypeTallies = [];
foreach ($vrCompleted as $doc) {
    $t = $doc['fields']['type'] ?? '';
    if ($t === '') {
        continue;
    }
    $vrTypeTallies[$t] = ($vrTypeTallies[$t] ?? 0) + 1;
}
arsort($vrTypeTallies);
$dosesByVaccineTypeTotal = array_sum($vrTypeTallies) ?: 1;
$dosesByVaccineType = [];
$vrColorIdx = 0;
foreach ($vrTypeTallies as $t => $count) {
    $dosesByVaccineType[] = ['label' => vr_type_label($t, $vrTypeMeta), 'value' => $count, 'pct' => round($count / $dosesByVaccineTypeTotal * 100, 1), 'color' => vr_type_color($t, $vrTypeMeta, $vrColorIdx, $vrFallbackColors)];
    $vrColorIdx++;
}

// ---------------------------------------------------------------------
// Doses Administered by Age Group (vertical bar) — real dose COUNTS per
// bucket (not a coverage %, which needs an undefined target population
// per bucket — see header comment), joined to each patient's real
// current age.
// ---------------------------------------------------------------------
function vr_age_bucket(int $age): string
{
    return match (true) {
        $age <= 5 => '0 - 5 yrs',
        $age <= 17 => '6 - 17 yrs',
        $age <= 39 => '18 - 39 yrs',
        $age <= 59 => '40 - 59 yrs',
        default => '60 & above',
    };
}
$vrAgeBucketOrder = ['0 - 5 yrs', '6 - 17 yrs', '18 - 39 yrs', '40 - 59 yrs', '60 & above'];
$vrAgeBucketColors = ['0 - 5 yrs' => '#93C5FD', '6 - 17 yrs' => '#86EFAC', '18 - 39 yrs' => '#FDBA74', '40 - 59 yrs' => '#C4B5FD', '60 & above' => '#67E8F9'];
$vrAgeBucketCounts = array_fill_keys($vrAgeBucketOrder, 0);
foreach ($vrCompleted as $doc) {
    $patient = $vrPatientById[$doc['fields']['patientId'] ?? ''] ?? null;
    if ($patient === null) {
        continue;
    }
    $vrAgeBucketCounts[vr_age_bucket((int) ($patient['age'] ?? 0))]++;
}
$coverageByAgeGroup = array_map(fn ($label) => ['label' => $label, 'value' => $vrAgeBucketCounts[$label], 'color' => $vrAgeBucketColors[$label]], $vrAgeBucketOrder);

// ---------------------------------------------------------------------
// Doses Administered Trend (line chart) — real, last 14 real days.
// ---------------------------------------------------------------------
$vrTrendBucketed = hc_last_n_days(array_column(array_map(fn ($d) => $d['fields'], $vrCompleted), 'dateAdministered'), 14);
$dosesAdministeredTrend = ['labels' => $vrTrendBucketed['labels'], 'values' => $vrTrendBucketed['values']];

// ---------------------------------------------------------------------
// Vaccine Administration Summary — table, real click-to-sort +
// pagination, filterable by the toolbar's real Vaccine Type dropdown.
// $fully/$partial/$booster/$coverage keys kept for the template's
// existing binding, RENAMED in meaning — see header comment.
// ---------------------------------------------------------------------
$vrByType = [];
foreach ($vrFacilityRecords as $doc) {
    $f = $doc['fields'];
    $t = $f['type'] ?? '';
    if ($t === '') {
        continue;
    }
    $vrByType[$t] ??= ['type' => vr_type_label($t, $vrTypeMeta), 'doses' => 0, 'fully' => 0, 'partial' => 0, 'booster' => 0];
    $vrByType[$t]['doses']++;
    if (($f['status'] ?? '') === 'completed') {
        $vrByType[$t]['fully']++;
        if (stripos($f['dose'] ?? '', 'boost') !== false) {
            $vrByType[$t]['booster']++;
        }
    } elseif (($f['status'] ?? '') === 'pending') {
        $vrByType[$t]['partial']++;
    }
}
$allVaccineAdministrationSummary = array_values(array_map(fn ($r) => $r + ['coverage' => $r['doses'] > 0 ? round($r['fully'] / $r['doses'] * 100, 1) : 0], $vrByType));
usort($allVaccineAdministrationSummary, fn ($a, $b) => $b['doses'] <=> $a['doses']);

$filteredVaccineAdministrationSummary = $vrTypeFilterLabel === ''
    ? $allVaccineAdministrationSummary
    : array_values(array_filter($allVaccineAdministrationSummary, fn ($r) => $r['type'] === $vrTypeFilterLabel));

$vaccineAdministrationTotal = [
    'doses'    => array_sum(array_column($filteredVaccineAdministrationSummary, 'doses')),
    'fully'    => array_sum(array_column($filteredVaccineAdministrationSummary, 'fully')),
    'partial'  => array_sum(array_column($filteredVaccineAdministrationSummary, 'partial')),
    'booster'  => array_sum(array_column($filteredVaccineAdministrationSummary, 'booster')),
];
$vaccineAdministrationTotal['coverage'] = $vaccineAdministrationTotal['doses'] > 0
    ? round($vaccineAdministrationTotal['fully'] / $vaccineAdministrationTotal['doses'] * 100, 1)
    : 0;

$vaccineAdministrationPaged = hc_paginate($filteredVaccineAdministrationSummary, 'vasPage', 5);
$vaccineAdministrationSummary = $vaccineAdministrationPaged['items'];
$vaccineAdministrationEntries = $vaccineAdministrationPaged['total'];
$vaccineAdministrationCurrentPage = $vaccineAdministrationPaged['page'];
$vaccineAdministrationTotalPages = $vaccineAdministrationPaged['totalPages'];

// ---------------------------------------------------------------------
// Vaccination by Barangay (Top 5) — real, joined via patientId -> this
// facility's own real patients' barangay field. Table-only Barangay
// filter (completed doses only). $fully key kept for the template's
// existing binding, RENAMED to "Unique Patients" — see header comment.
// ---------------------------------------------------------------------
$vrByBarangay = [];
foreach ($vrCompleted as $doc) {
    $patient = $vrPatientById[$doc['fields']['patientId'] ?? ''] ?? null;
    $bgy = trim((string) ($patient['barangay'] ?? ''));
    if ($bgy === '') {
        continue;
    }
    $vrByBarangay[$bgy] ??= ['barangay' => $bgy, 'doses' => 0, 'patients' => []];
    $vrByBarangay[$bgy]['doses']++;
    $vrByBarangay[$bgy]['patients'][$doc['fields']['patientId']] = true;
}
$allVaccinationByBarangay = [];
foreach ($vrByBarangay as $row) {
    $allVaccinationByBarangay[] = [
        'barangay' => $row['barangay'],
        'doses' => $row['doses'],
        'fully' => count($row['patients']), // real unique-patient count, not "fully vaccinated" — see header comment
        'coverage' => $row['doses'] > 0 ? round(count($row['patients']) / $row['doses'] * 100, 1) : 0,
    ];
}
usort($allVaccinationByBarangay, fn ($a, $b) => $b['doses'] <=> $a['doses']);

$barangayFilterOptions = array_merge(['All Barangays'], array_column($allVaccinationByBarangay, 'barangay'));
$vrBarangayFilter = (string) ($_GET['barangay'] ?? '');
$filteredVaccinationByBarangay = $vrBarangayFilter === ''
    ? $allVaccinationByBarangay
    : array_values(array_filter($allVaccinationByBarangay, fn ($r) => $r['barangay'] === $vrBarangayFilter));

$vaccinationByBarangayPaged = hc_paginate($filteredVaccinationByBarangay, 'vbbPage', 5);
$vaccinationByBarangay = $vaccinationByBarangayPaged['items'];
$vaccinationByBarangayTotal = $vaccinationByBarangayPaged['total'];
$vaccinationByBarangayCurrentPage = $vaccinationByBarangayPaged['page'];
$vaccinationByBarangayTotalPages = $vaccinationByBarangayPaged['totalPages'];
