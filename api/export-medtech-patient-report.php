<?php
/**
 * =====================================================================
 * Export "My Patients Report" — CSV (Medical Technologist)
 * ---------------------------------------------------------------------
 * Re-derives the exact same date-filtered rows medical_technician/analytics_reports.php's
 * "My Patients Report" table shows (?from=/?to=, same medical_records
 * 'laboratory' docs scoped to provider === this technologist's name)
 * and streams them as a real CSV download — see
 * includes/shared/patient_consultation_report.php's header comment for
 * the full three-role design.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/patient_consultation_report.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    exit('Not authorized.');
}

$range = hc_report_date_range_from_get();
$rows = hc_lab_report_rows($_SESSION['name'] ?? '', $range['from'], $range['to']);

write_audit_log('viewed', 'Analytics & Reports', 'Exported My Patients Report (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ', ' . $range['from'] . ' to ' . $range['to'] . ').');

hc_stream_patient_report_csv('my-patients-report_' . $range['from'] . '_to_' . $range['to'] . '.csv', $rows);
