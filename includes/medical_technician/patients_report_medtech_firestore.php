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

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$myReportRange = hc_report_date_range_from_get();
$myPatientReportRows = hc_lab_report_rows($_SESSION['name'] ?? '', $myReportRange['from'], $myReportRange['to']);
