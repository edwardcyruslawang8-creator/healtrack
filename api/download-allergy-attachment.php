<?php
/**
 * =====================================================================
 * Download Allergy Status-Change Attachment (api/download-allergy-attachment.php)
 * ---------------------------------------------------------------------
 * Real download for the "Attachment" column on every role's
 * allergy_details.php history table. Mirrors api/download-document.php
 * exactly, except the metadata it reads lives on a specific
 * allergies/{id}.history[] entry (addressed by array index, since
 * history entries are append-only and never reordered/removed) rather
 * than its own top-level Firestore collection — see
 * includes/shared/allergy_attachment_storage.php's header comment for
 * why this is a separate, parallel storage area from patient_documents.
 *
 * subadmin may VIEW/download an attachment here (read-only, matches
 * its existing read-only access to the rest of this page) even though
 * subadmin never gets a UI entry point to CREATE one — that gate lives
 * in api/update-allergy-clinical-status.php, not here.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document(), hc_document_mime_for_extension()
require_once __DIR__ . '/../includes/shared/allergy_attachment_storage.php'; // hc_allergy_attachment_storage_dir()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

if (!in_array($_SESSION['role'] ?? null, ['doctor', 'healthcare_staff', 'subadmin'], true)) {
    http_response_code(403);
    echo 'Not authorized.';
    exit;
}

$allergyId = trim((string) ($_GET['allergyId'] ?? ''));
$historyIndex = (int) ($_GET['historyIndex'] ?? -1);
if ($allergyId === '' || $historyIndex < 0) {
    http_response_code(422);
    echo 'Missing attachment reference.';
    exit;
}

$allergy = $firestore->getDocument('allergies', $allergyId);
if ($allergy === null) {
    http_response_code(404);
    echo 'This record could not be found.';
    exit;
}

$patientId = (string) ($allergy['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo 'This record could not be found.';
    exit;
}
$patient['id'] = $patientId;

if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo 'This record could not be found.';
    exit;
}

$history = is_array($allergy['history'] ?? null) ? $allergy['history'] : [];
$entry = $history[$historyIndex] ?? null;
$attachment = $entry['attachment'] ?? null;
if ($attachment === null || empty($attachment['storedFileName'])) {
    http_response_code(404);
    echo 'No attachment is on file for this entry.';
    exit;
}

$storedFileName = (string) $attachment['storedFileName'];
$filePath = hc_allergy_attachment_storage_dir() . '/' . $storedFileName;
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'This file is no longer available.';
    exit;
}

$ext = strtolower(pathinfo($storedFileName, PATHINFO_EXTENSION));
$downloadName = (string) ($attachment['originalName'] ?? ('attachment.' . $ext));
$disposition = (($_GET['view'] ?? '') === '1') ? 'inline' : 'attachment';

$allergen = (string) ($allergy['allergen'] ?? 'allergy');
write_audit_log('viewed', 'Patient Management', ($disposition === 'inline' ? 'Viewed' : 'Downloaded') . ' allergy status-change attachment "' . $downloadName . '" (' . $allergen . ') for ' . ($patient['name'] ?? 'a patient') . '.');

header('Content-Type: ' . hc_document_mime_for_extension($ext));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
