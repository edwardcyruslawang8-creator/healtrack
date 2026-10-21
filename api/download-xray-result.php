<?php
/**
 * =====================================================================
 * Download/View X-ray Result Document (api/download-xray-result.php)
 * ---------------------------------------------------------------------
 * Real download for the uploaded X-ray result file (see
 * includes/shared/xray_result_storage.php's header comment and
 * api/complete-xray-result.php, which writes it) — the single real
 * place a stored X-ray result file ever gets read back. Gated to the
 * clinical staff roles that can already reach a patient's lab/x-ray
 * result details (same role list laboratory_result_details.php-family
 * pages across medical_technician/radio_technologist/doctor/
 * healthcare_staff/subadmin already allow); addressed by a bare ?id=
 * (the xray_results doc id), so it can't trust a referring page already
 * filtered what's visible.
 *
 * Also doubles as the real "View" action (?view=1) — same file, same
 * access check, just Content-Disposition: inline instead of attachment
 * so the browser renders the PDF/image directly (opened in a new tab)
 * instead of saving it. Same two-use-one-endpoint pattern as
 * api/download-document.php.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/xray_result_storage.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (!in_array($_SESSION['role'] ?? null, ['medical_technician', 'radio_technologist', 'doctor', 'healthcare_staff', 'subadmin'], true)) {
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

$doc = $firestore->getDocument('xray_results', $id);
if ($doc === null) {
    http_response_code(404);
    echo 'This result could not be found.';
    exit;
}

$storedFileName = (string) ($doc['storedFileName'] ?? '');
$filePath = $storedFileName !== '' ? hc_xray_result_storage_dir() . '/' . $storedFileName : '';
if ($storedFileName === '' || !is_file($filePath)) {
    http_response_code(404);
    echo 'This result\'s file is no longer available.';
    exit;
}

$ext = strtolower(pathinfo($storedFileName, PATHINFO_EXTENSION));
$downloadName = (string) ($doc['attachedFileName'] ?? ('xray-result.' . $ext));
$disposition = (($_GET['view'] ?? '') === '1') ? 'inline' : 'attachment';

$patientName = '';
$patientId = (string) ($doc['patientId'] ?? '');
if ($patientId !== '') {
    $patient = $firestore->getDocument('patients', $patientId);
    $patientName = $patient['name'] ?? '';
}

write_audit_log('viewed', 'Laboratory & X-ray', ($disposition === 'inline' ? 'Viewed' : 'Downloaded') . ' X-ray result file "' . $downloadName . '"' . ($patientName !== '' ? ' for ' . $patientName . '.' : '.'));

header('Content-Type: ' . hc_xray_result_mime_for_extension($ext));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
