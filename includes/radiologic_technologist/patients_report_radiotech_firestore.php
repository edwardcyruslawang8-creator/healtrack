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

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Radiologic Technologist',
    'role'     => 'Radiologic Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$myReportRange = hc_report_date_range_from_get();
$myPatientReportRows = hc_xray_report_rows($_SESSION['name'] ?? '', $myReportRange['from'], $myReportRange['to']);
