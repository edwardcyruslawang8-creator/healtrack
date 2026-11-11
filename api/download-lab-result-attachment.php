<?php
/**
 * =====================================================================
 * Download/View Lab Result Attachment (api/download-lab-result-attachment.php)
 * ---------------------------------------------------------------------
 * Real download for the generated lab result report (see
 * includes/shared/lab_result_attachment.php, which writes it, and
 * includes/shared/lab_result_report.php, which builds it) — the single
 * real place a stored lab result attachment file ever gets read back.
 * Gated to the roles that can already reach a patient's lab result
 * details (same role list laboratory_result_details.php-family pages
 * across medical_technician/doctor/healthcare_staff/subadmin already
 * allow); addressed by a bare ?id= (the lab_results doc id), same
 * reasoning as api/download-xray-result.php's own copy of this comment
 * — it can't trust a referring page already filtered what's visible.
 *
 * Also doubles as the real "View"/"Print" action (?view=1) — same file,
 * same access check, just Content-Disposition: inline instead of
 * attachment so the browser renders the HTML report directly (opened in
 * a new tab), where staff can print it with Ctrl+P. Same two-use-one-
 * endpoint pattern as api/download-document.php / api/download-xray-result.php.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (!in_array($_SESSION['role'] ?? null, ['medical_technician', 'doctor', 'healthcare_staff', 'subadmin'], true)) {
    http_response_code(403);
    echo 'Not authorized.';
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(422);
    echo 'Missing result id.';
    exit;
}

$doc = $firestore->getDocument('lab_results', $id);
if ($doc === null) {
    http_response_code(404);
    echo 'This result could not be found.';
    exit;
}

$storedFileName = (string) ($doc['storedFileName'] ?? '');
$filePath = $storedFileName !== '' ? hc_document_storage_dir() . '/' . $storedFileName : '';
if ($storedFileName === '' || !is_file($filePath)) {
    http_response_code(404);
    echo 'This result\'s file is no longer available.';
    exit;
}

$ext = strtolower(pathinfo($storedFileName, PATHINFO_EXTENSION));
$downloadName = (string) ($doc['attachedFileName'] ?? ('lab-result.' . $ext));
$disposition = (($_GET['view'] ?? '') === '1') ? 'inline' : 'attachment';

$patientName = '';
$patientId = (string) ($doc['patientId'] ?? '');
if ($patientId !== '') {
    $patient = $firestore->getDocument('patients', $patientId);
    $patientName = $patient['name'] ?? '';
}

write_audit_log('viewed', 'Laboratory & X-ray', ($disposition === 'inline' ? 'Viewed' : 'Downloaded') . ' lab result file "' . $downloadName . '"' . ($patientName !== '' ? ' for ' . $patientName . '.' : '.'));

header('Content-Type: ' . hc_document_mime_for_extension($ext));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
