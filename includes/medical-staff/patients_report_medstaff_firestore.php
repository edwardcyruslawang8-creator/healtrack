<?php
/**
 * =====================================================================
 * Patients Report (Medical Staff) — Data
 * ---------------------------------------------------------------------
 * The "Patients Report" child under the Analytics & Reports dropdown
 * (sibling of analytics_reports.php's "Overview") — a date-filtered,
 * exportable list of consultations this staff member set. See
 * includes/shared/patient_consultation_report.php's header comment for
 * the full three-role design (doctor/medical-staff/medical_technician).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../shared/patient_consultation_report.php'; // hc_report_date_range_from_get(), hc_consultation_report_rows()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Staff',
    'role'     => 'Medical Staff',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

$myConsultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => $c['createdBy'] === $myUid));

$myReportRange = hc_report_date_range_from_get();
$myPatientReportRows = hc_consultation_report_rows($myConsultations, $myReportRange['from'], $myReportRange['to']);
