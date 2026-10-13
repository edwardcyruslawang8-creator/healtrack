<?php
/**
 * =====================================================================
 * Patient Laboratory & X-ray (Doctor) — Data
 * ---------------------------------------------------------------------
 * Reuses includes/doctor/patient_profile_doctor_firestore.php for
 * everything about the patient themselves ($doctorProfile, $patient,
 * $profileTabs) — this page IS that same patient's profile, just
 * viewing the "Laboratory & X-ray" tab. The actual Firestore queries
 * live in includes/firestore/lab_results.php and
 * includes/firestore/xray_results.php — shared with every other role's
 * identical page.
 * =====================================================================
 */

require_once __DIR__ . '/patient_profile_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $patient, $profileTabs
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php';
require_once __DIR__ . '/../shared/pagination_helpers.php'; // hc_paginate(), hc_render_pagination_nav(), hc_matches_date_range_key()

// Re-mark which tab is active — this page shows "Laboratory & X-ray"
// instead of patient_profile.php's "Overview".
foreach ($profileTabs as &$tab) {
    $tab['active'] = ($tab['key'] === 'lab_xray');
}
unset($tab);

$statusMeta = [
    'completed' => ['label' => 'Completed', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'pending'   => ['label' => 'Pending',   'bg' => '#FDECDD', 'color' => '#E8710A'],
];

// ---------------------------------------------------------------------
// Real laboratory results for this patient — date range filter (Today /
// This Week / This Month / All Time) is real now (used to be an
// unwired button showing a hardcoded "All time" label).
// ---------------------------------------------------------------------
$labTestTypeOptions = ['All Test Types', 'Hematology', 'Chemistry', 'Urinalysis', 'Microbiology', 'Serology'];
$labStatusOptions = ['All Status', 'Completed', 'Pending'];

$dateRangeOptionLabels = ['all' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month'];

$labRangeFilter = array_key_exists($_GET['labRange'] ?? '', $dateRangeOptionLabels) ? $_GET['labRange'] : 'all';
$labRangeLabel = $dateRangeOptionLabels[$labRangeFilter];
$allLabResults = hc_get_lab_results_for_patient($patient['id']);
$filteredLabResults = array_values(array_filter($allLabResults, fn ($r) => hc_matches_date_range_key($r['datetime'] ?? '', $labRangeFilter)));

$labResultsPerPage = 5;
$labResultsPagination = hc_paginate($filteredLabResults, 'labPage', $labResultsPerPage);
$labResults = $labResultsPagination['items'];
$labResultsTotal = $labResultsPagination['total'];
$labResultsTotalPages = $labResultsPagination['totalPages'];
$labResultsCurrentPage = $labResultsPagination['page'];

// ---------------------------------------------------------------------
// Real x-ray results for this patient — same real date range filter.
// ---------------------------------------------------------------------
$xrayBodyPartOptions = ['All Body Part', 'Chest', 'Lower Back', 'Knee (Right)', 'Knee (Left)', 'Skull', 'Abdomen'];
$xrayStatusOptions = ['All Status', 'Completed', 'Pending'];

$xrayRangeFilter = array_key_exists($_GET['xrayRange'] ?? '', $dateRangeOptionLabels) ? $_GET['xrayRange'] : 'all';
$xrayRangeLabel = $dateRangeOptionLabels[$xrayRangeFilter];
$allXrayResults = hc_get_xray_results_for_patient($patient['id']);
$filteredXrayResults = array_values(array_filter($allXrayResults, fn ($r) => hc_matches_date_range_key($r['datetime'] ?? '', $xrayRangeFilter)));

$xrayResultsPerPage = 10;
$xrayResultsPagination = hc_paginate($filteredXrayResults, 'xrayPage', $xrayResultsPerPage);
$xrayResults = $xrayResultsPagination['items'];
$xrayResultsTotal = $xrayResultsPagination['total'];
$xrayResultsTotalPages = $xrayResultsPagination['totalPages'];
$xrayResultsCurrentPage = $xrayResultsPagination['page'];
