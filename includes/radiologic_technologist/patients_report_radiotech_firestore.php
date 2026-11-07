<?php
/**
 * =====================================================================
 * Patients Report (Radiologic Technologist) — Data
 * ---------------------------------------------------------------------
 * The "My Reports" child under the Analytics & Reports dropdown
 * (sibling of analytics_reports.php's "Overview") — a date-filtered,
 * exportable list of X-ray exams this technologist personally
 * completed. See includes/shared/patient_consultation_report.php's
 * header comment for the full design, including why this reads
 * medical_records.provider rather than xray_results (that collection
 * never records who completed an exam).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/patient_consultation_report.php'; // hc_report_date_range_from_get(), hc_xray_report_rows()
require_once __DIR__ . '/../shared/query_helpers.php'; // hc_url_with_params() — used by includes/shared/simple_filter_dropdown.php, this page's real "X-ray Test" filter button

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$myReportRange = hc_report_date_range_from_get();
$myAllPatientReportRows = hc_xray_report_rows($_SESSION['name'] ?? '', $myReportRange['from'], $myReportRange['to']);

// Real "X-ray Test" filter button (includes/shared/simple_filter_dropdown.php,
// same real-options-from-data convention includes/public/medicine-data.php's
// hc_medicine_filter_options() uses) — built from this technologist's
// ENTIRE real history, not just whatever the currently-selected date
// range happens to contain (a narrow/empty range would otherwise leave
// the dropdown with nothing to pick, even though real exams of theirs
// exist outside that window).
$myReportTestValues = array_values(array_unique(array_map(
    fn ($r) => $r['detail'],
    hc_xray_report_rows($_SESSION['name'] ?? '', '0001-01-01', '9999-12-31')
)));
sort($myReportTestValues, SORT_NATURAL | SORT_FLAG_CASE);
$myReportTestOptions = array_merge(['All X-ray Exams'], $myReportTestValues);

$myReportTestFilter = (string) ($_GET['test'] ?? '');
$myPatientReportRows = ($myReportTestFilter !== '' && in_array($myReportTestFilter, $myReportTestValues, true))
    ? array_values(array_filter($myAllPatientReportRows, fn ($r) => $r['detail'] === $myReportTestFilter))
    : $myAllPatientReportRows;
