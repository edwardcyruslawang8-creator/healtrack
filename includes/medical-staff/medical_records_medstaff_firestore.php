<?php
/**
 * =====================================================================
 * Patient Medical Records (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/patient_profile_medstaff_firestore.php for everything
 * about the patient themselves ($staffProfile, $patient, $profileTabs)
 * — this page IS that same patient's profile, just viewing the
 * "Medical Records" tab instead of "Overview". The actual Firestore
 * query lives in includes/firestore/medical_records.php's
 * hc_get_medical_records_for_patient() — shared with subadmin's
 * identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_medstaff_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/medical_records.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

// Re-mark which tab is active — this page shows "Medical Records"
// instead of patient_profile.php's "Overview".
foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'medical_records');
}
unset($tab);

// ---------------------------------------------------------------------
// Record type -> icon/color lookup, so the table loop doesn't hardcode
// styling per row.
// ---------------------------------------------------------------------
$recordTypeMeta = [
    'consultation' => ['label' => 'Consultation', 'icon' => 'bi-clipboard2-pulse-fill', 'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'laboratory'   => ['label' => 'Laboratory',   'icon' => 'bi-eyedropper',            'bg' => '#EFE7FB', 'color' => '#7C3AED'],
    'xray'         => ['label' => 'X-ray',        'icon' => 'bi-lungs-fill',            'bg' => '#E8F0FE', 'color' => '#1A73E8'],
    'prescription' => ['label' => 'Prescription', 'icon' => 'bi-prescription2',         'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'procedure'    => ['label' => 'Procedure',    'icon' => 'bi-bandaid-fill',          'bg' => '#FCE4E4', 'color' => '#D93025'],
    'referral'     => ['label' => 'Referral',     'icon' => 'bi-send-fill',             'bg' => '#FDECDD', 'color' => '#E8710A'],
];

$allRecordsForPatient = hc_get_medical_records_for_patient($patient['id']);

// ---------------------------------------------------------------------
// Record categories (left panel) — real counts per type.
// ---------------------------------------------------------------------
$categoryFilter = $_GET['category'] ?? 'all';
$categoryDefs = [
    ['key' => 'all',          'label' => 'All Records'],
    ['key' => 'consultation', 'label' => 'Consultations'],
    ['key' => 'laboratory',   'label' => 'Laboratory Results'],
    ['key' => 'xray',         'label' => 'X-ray Results'],
    ['key' => 'prescription', 'label' => 'Prescriptions'],
    ['key' => 'procedure',    'label' => 'Procedures'],
    ['key' => 'referral',     'label' => 'Referrals'],
];
$recordCategories = array_map(function ($cat) use ($allRecordsForPatient, $categoryFilter) {
    $count = $cat['key'] === 'all'
        ? count($allRecordsForPatient)
        : count(array_filter($allRecordsForPatient, fn ($r) => ($r['type'] ?? '') === $cat['key']));
    return ['key' => $cat['key'], 'label' => $cat['label'], 'count' => $count, 'active' => $cat['key'] === $categoryFilter];
}, $categoryDefs);

$filteredRecords = $categoryFilter === 'all'
    ? $allRecordsForPatient
    : array_values(array_filter($allRecordsForPatient, fn ($r) => ($r['type'] ?? '') === $categoryFilter));

// ---------------------------------------------------------------------
// Date range filter (Today / This Week / This Month / All Time) — the
// "Filter" button above the table, real now (used to be unwired).
// ---------------------------------------------------------------------
$mrcDateRangeLabels = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];
$mrcDateRangeFilter = array_key_exists($_GET['daterange'] ?? '', $mrcDateRangeLabels) ? $_GET['daterange'] : 'all';
$mrcDateRangeLabel = $mrcDateRangeLabels[$mrcDateRangeFilter];
$filteredRecords = array_values(array_filter($filteredRecords, fn ($r) => hc_matches_date_range_key($r['date'] ?? '', $mrcDateRangeFilter)));

// ---------------------------------------------------------------------
// Medical records — real, paginated.
// ---------------------------------------------------------------------
$medicalRecordsPerPage = 6;
$medicalRecordsPagination = hc_paginate($filteredRecords, 'page', $medicalRecordsPerPage);
$medicalRecords = $medicalRecordsPagination['items'];
$medicalRecordsTotal = $medicalRecordsPagination['total'];
$medicalRecordsTotalPages = $medicalRecordsPagination['totalPages'];
$medicalRecordsCurrentPage = $medicalRecordsPagination['page'];
