<?php
/**
 * =====================================================================
 * Export "My Reports" — CSV (Radio Technologist)
 * ---------------------------------------------------------------------
 * Re-derives the exact same date-filtered rows
 * radio_technologist/patients_report.php's "My Reports" table shows
 * (?from=/?to=, hc_xray_report_rows() scoped to this technologist's own
 * name) and streams them as a real CSV download — see
 * includes/shared/patient_consultation_report.php's header comment for
 * the full per-role design.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/patient_consultation_report.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    http_response_code(403);
    exit('Not authorized.');
}

$range = hc_report_date_range_from_get();
$rows = hc_xray_report_rows($_SESSION['name'] ?? '', $range['from'], $range['to']);

write_audit_log('viewed', 'Analytics & Reports', 'Exported My Reports (' . count($rows) . ' row' . (count($rows) === 1 ? '' : 's') . ', ' . $range['from'] . ' to ' . $range['to'] . ').');

hc_stream_patient_report_csv('my-reports_' . $range['from'] . '_to_' . $range['to'] . '.csv', $rows);
