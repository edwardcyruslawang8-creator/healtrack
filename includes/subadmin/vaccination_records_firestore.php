<?php
/**
 * =====================================================================
 * Patient Vaccination Records — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_firestore.php for everything about the
 * patient themselves — this page IS that same patient's profile, just
 * viewing the "Vaccination Records" tab. The actual Firestore query
 * lives in includes/firestore/vaccination_records.php's
 * hc_get_vaccination_records_for_patient() — shared with
 * medical-staff's identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/vaccination_records.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'vaccination');
}
unset($tab);

// Vaccine category -> icon/color meta for the table's type badge.
$vaccineTypeMeta = [
    'covid19'     => ['label' => 'COVID-19',    'icon' => 'bi-shield-fill-check', 'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
    'tetanus'     => ['label' => 'Tetanus',     'icon' => 'bi-shield-fill-check', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'influenza'   => ['label' => 'Influenza',   'icon' => 'bi-shield-fill-check', 'bg' => '#E3F6FB', 'color' => '#12A6C6'],
    'hepatitis_b' => ['label' => 'Hepatitis B', 'icon' => 'bi-shield-fill-check', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
];

// ---------------------------------------------------------------------
// Real vaccination records for this patient, newest-administered first.
// ---------------------------------------------------------------------
$allVaccinationRecords = hc_get_vaccination_records_for_patient($patient['id']);

// The record shown in the "Vaccine Record Details" panel below the
// table — the most recent one, or an empty-state placeholder shape if
// this patient has no vaccination records at all. Resolved from the
// UNFILTERED list, so this stays the same regardless of the date
// filter below (a real card about this patient's latest record, not
// about whatever the table happens to be currently showing).
$selectedVaccineRecord = $allVaccinationRecords[0] ?? hc_empty_vaccine_record();

// ---------------------------------------------------------------------
// Date range filter (Today / This Week / This Month / All Time) — the
// "Filter" button above the table, real now (used to be unwired).
// ---------------------------------------------------------------------
$vrcDateRangeLabels = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$vrcDateRangeFilter = array_key_exists($_GET['daterange'] ?? '', $vrcDateRangeLabels) ? $_GET['daterange'] : 'all';
$vrcDateRangeLabel = $vrcDateRangeLabels[$vrcDateRangeFilter];
$filteredVaccinationRecords = array_values(array_filter($allVaccinationRecords, fn ($v) => hc_matches_date_range_key($v['dateAdministered'] ?? '', $vrcDateRangeFilter)));

$vaccinationRecordsPerPage = 10;
$vaccinationRecordsPagination = hc_paginate($filteredVaccinationRecords, 'page', $vaccinationRecordsPerPage);
$vaccinationRecords = $vaccinationRecordsPagination['items'];
$vaccinationRecordsTotal = $vaccinationRecordsPagination['total'];
$vaccinationRecordsTotalPages = $vaccinationRecordsPagination['totalPages'];
$vaccinationRecordsCurrentPage = $vaccinationRecordsPagination['page'];
