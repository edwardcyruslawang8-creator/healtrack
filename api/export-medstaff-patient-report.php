<?php
/**
 * =====================================================================
 * Export "My Patients Report" — CSV (Medical Staff)
 * ---------------------------------------------------------------------
 * Re-derives the exact same date-filtered rows medical-staff/analytics_reports.php's
 * "My Patients Report" table shows (?from=/?to=, same consultations
 * scoped to createdBy === this staff member's uid) and streams them as
 * a real CSV download — see includes/shared/patient_consultation_report.php's
 * header comment for the full three-role design.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';
require_once __DIR__ . '/../includes/shared/patient_consultation_report.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    http_response_code(403);
    exit('Not authorized.');
}

$myUid = $_SESSION['uid'] ?? '';
$myConsultations = array_values(array_filter(hc_get_all_consultations(), fn ($c) => $c['createdBy'] === $myUid));

$range = hc_report_date_range_from_get();
$rows = hc_consultation_report_rows($myConsultations, $range['from'], $range['to']);

write_audit_log('viewed', 'Analytics & Reports', 'Exported My Patients Report (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ', ' . $range['from'] . ' to ' . $range['to'] . ').');

hc_stream_patient_report_csv('my-patients-report_' . $range['from'] . '_to_' . $range['to'] . '.csv', $rows);
