<?php
/**
 * =====================================================================
 * Patient Vaccination Records (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient, $profileTabs)
 * — re-marks the "vaccination" tab active here. The actual Firestore
 * query lives in includes/firestore/vaccination_records.php's
 * hc_get_vaccination_records_for_patient() — shared with subadmin's
 * identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/vaccination_records.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'vaccination');
}
unset($tab);

// Vaccine category -> icon/color meta for the table's type badge.
$vaccineTypeMeta = hc_vaccine_type_meta(); // includes/firestore/vaccination_records.php

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

// ---------------------------------------------------------------------
// "+ Add Vaccination Record" modal (includes/medical-staff/add_vaccination_modal.php)
// ---------------------------------------------------------------------
// Vaccine Name suggestions: common vaccines (including ones a patient
// may have received elsewhere before registering) plus every vaccine
// currently in this system's real inventory. Still free text — a
// vaccination card can name anything.
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items()
$vaccineNameSuggestions = array_merge([
    'BCG', 'Hepatitis B', 'Pentavalent (DPT-HepB-Hib)', 'Oral Polio Vaccine (OPV)',
    'Inactivated Polio Vaccine (IPV)', 'Pneumococcal Conjugate Vaccine (PCV)',
    'Pneumococcal Polysaccharide Vaccine (PPSV23)', 'Measles-Rubella (MR)',
    'Measles-Mumps-Rubella (MMR)', 'Tetanus Toxoid (TT)', 'Tetanus-Diphtheria (Td)',
    'Influenza', 'HPV', 'Anti-Rabies', 'COVID-19 (Pfizer-BioNTech)', 'COVID-19 (Moderna)',
    'COVID-19 (Sinovac/CoronaVac)', 'COVID-19 (AstraZeneca)', 'COVID-19 (Janssen)',
], array_column(hc_get_inventory_items('vaccine'), 'name'));
$vaccineNameSuggestions = array_values(array_unique($vaccineNameSuggestions));
sort($vaccineNameSuggestions, SORT_NATURAL | SORT_FLAG_CASE);

// Date Administered bounds: never before birth, never in the future. A
// vaccine given at this facility also can't predate registration —
// enforced again in api/add-vaccination-record.php. $patient only has
// display-formatted dates ("Jan 5, 2024"), which strtotime() parses.
$avxBirthDateRaw = $patient['birthDate'] !== '—' && strtotime($patient['birthDate']) ? date('Y-m-d', strtotime($patient['birthDate'])) : '';
$avxRegisteredRaw = $patient['registeredOn'] !== '—' && strtotime($patient['registeredOn']) ? date('Y-m-d', strtotime($patient['registeredOn'])) : '';
