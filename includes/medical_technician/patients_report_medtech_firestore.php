<?php
/**
 * =====================================================================
 * Patients Report (Medical Technologist) — Data
 * ---------------------------------------------------------------------
 * The "Patients Report" child under the Analytics & Reports dropdown
 * (sibling of analytics_reports.php's "Overview") — a date-filtered,
 * exportable list of tests this technologist personally completed. See
 * includes/shared/patient_consultation_report.php's header comment for
 * the full three-role design (doctor/medical-staff/medical_technician),
 * including why this reads medical_records.provider rather than
 * lab_results/xray_results (neither records who completed a test).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/patient_consultation_report.php'; // hc_report_date_range_from_get(), hc_lab_report_rows()
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params() — used by includes/shared/simple_filter_dropdown.php, this page's real "Laboratory Test" filter button
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_lab_test_catalog() — this page's real "Laboratory Test" filter options

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$myReportRange = hc_report_date_range_from_get();
$myAllPatientReportRows = hc_lab_report_rows($_SESSION['name'] ?? '', $myReportRange['from'], $myReportRange['to']);

// Real "Laboratory Test" filter button (includes/shared/simple_filter_dropdown.php) —
// every real test this facility's catalog offers
// (medical_technician/lab_test_catalog.php's own hc_lab_test_catalog()),
// not just whatever this technologist happens to have personally
// completed so far, so every real test type is always pickable. Each
// catalog testName is prefixed "Laboratory — " to match the exact
// 'detail' format api/complete-lab-result.php's/complete-*-test.php's
// own 'title' field always writes (see hc_lab_report_rows() above).
$myReportTestValues = [];
foreach (hc_lab_test_catalog() as $category => $testNames) {
    foreach ($testNames as $testName) {
        $myReportTestValues[] = 'Laboratory — ' . $testName;
    }
}
$myReportTestValues = array_values(array_unique($myReportTestValues));
sort($myReportTestValues, SORT_NATURAL | SORT_FLAG_CASE);
$myReportTestOptions = array_merge(['All Laboratory Tests'], $myReportTestValues);

$myReportTestFilter = (string) ($_GET['test'] ?? '');
$myPatientReportRows = ($myReportTestFilter !== '' && in_array($myReportTestFilter, $myReportTestValues, true))
    ? array_values(array_filter($myAllPatientReportRows, fn ($r) => $r['detail'] === $myReportTestFilter))
    : $myAllPatientReportRows;
