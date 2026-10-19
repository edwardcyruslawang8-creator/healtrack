<?php
/**
 * =====================================================================
 * Patient Vaccination Records (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/doctor/patient_profile_doctor_firestore.php for
 * everything about the patient themselves ($doctorProfile, $patient,
 * $profileTabs) — re-marks the "vaccination" tab active here. The
 * actual Firestore query lives in
 * includes/firestore/vaccination_records.php's
 * hc_get_vaccination_records_for_patient() — shared with every other
 * role's identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/vaccination_records.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav()

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

// ---------------------------------------------------------------------
// Date range filter — a real From/To range (any span, not a preset
// Today/This Week/This Month bucket), same pattern as the History
// tab's hisFrom/hisTo. Either end can be left blank for an open-ended
// range.
// ---------------------------------------------------------------------
$vrcDateFrom = trim($_GET['vrcFrom'] ?? '');
$vrcDateTo = trim($_GET['vrcTo'] ?? '');
$filteredVaccinationRecords = array_values(array_filter($allVaccinationRecords, function ($v) use ($vrcDateFrom, $vrcDateTo) {
    if ($vrcDateFrom !== '' && $v['rawDateAdministered'] < $vrcDateFrom) {
        return false;
    }
    if ($vrcDateTo !== '' && $v['rawDateAdministered'] > $vrcDateTo) {
        return false;
    }
    return true;
}));

$vaccinationRecordsPerPage = 10;
$vaccinationRecordsPagination = hc_paginate($filteredVaccinationRecords, 'page', $vaccinationRecordsPerPage);
$vaccinationRecords = $vaccinationRecordsPagination['items'];
$vaccinationRecordsTotal = $vaccinationRecordsPagination['total'];
$vaccinationRecordsTotalPages = $vaccinationRecordsPagination['totalPages'];
$vaccinationRecordsCurrentPage = $vaccinationRecordsPagination['page'];
