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
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_xray_exam_catalog() — this page's real "X-ray Test" filter options

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

// Real "X-ray Test" filter button (includes/shared/simple_filter_dropdown.php) —
// every real exam type this app's catalog offers (hc_xray_exam_catalog(),
// the same list doctor/active_consultation.php's own X-ray referral
// checkboxes read from), not just whatever this technologist happens to
// have personally completed so far, so every real exam type is always
// pickable. hc_xray_report_rows()'s own 'detail' is already the bare
// exam name with no prefix (see that function's own 'detail' assignment
// — unlike the Laboratory side, which keeps a "Laboratory — " prefix),
// so catalog keys match it directly with no transformation needed.
$myReportTestValues = array_values(array_unique(array_keys(hc_xray_exam_catalog())));
sort($myReportTestValues, SORT_NATURAL | SORT_FLAG_CASE);
$myReportTestOptions = array_merge(['All X-ray Exams'], $myReportTestValues);

$myReportTestFilter = (string) ($_GET['test'] ?? '');
$myPatientReportRows = ($myReportTestFilter !== '' && in_array($myReportTestFilter, $myReportTestValues, true))
    ? array_values(array_filter($myAllPatientReportRows, fn ($r) => $r['detail'] === $myReportTestFilter))
    : $myAllPatientReportRows;
