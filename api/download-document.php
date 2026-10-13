<?php
/**
 * =====================================================================
 * Download Patient Document (api/download-document.php)
 * ---------------------------------------------------------------------
 * Real download for every Documents page's "Download" button
 * (assets/js/document-upload.js), and the single real place a stored
 * file ever gets read back — see includes/shared/document_storage.php's
 * header comment for why these live on local disk rather than Firebase
 * Cloud Storage. Re-checks the signed-in user's access to the owning
 * patient before ever streaming bytes back, exactly like
 * api/upload-document.php does on the way in — a Documents page only
 * ever lists documents for a patient this user can already see, but
 * this endpoint is addressed by a bare ?id=, so it can't trust that.
 *
 * Also doubles as the real "View" action (?view=1) — same file, same
 * access check, just Content-Disposition: inline instead of attachment
 * so the browser renders the PDF/image directly (opened in a new tab
 * via target="_blank") instead of saving it. One endpoint, two real
 * uses, rather than duplicating the whole read-and-stream logic for a
 * second file.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php';

if (!in_array($_SESSION['role'] ?? null, ['doctor', 'healthcare_staff', 'subadmin'], true)) {
    http_response_code(403);
    echo 'Not authorized.';
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(422);
    echo 'Missing document id.';
    exit;
}

$doc = $firestore->getDocument('patient_documents', $id);
if ($doc === null) {
    http_response_code(404);
    echo 'This document could not be found.';
    exit;
}

$patientId = (string) ($doc['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    http_response_code(404);
    echo 'This document could not be found.';
    exit;
}
$patient['id'] = $patientId;

if (!hc_user_can_access_patient_document($patient)) {
    http_response_code(404);
    echo 'This document could not be found.';
    exit;
}

$storedFileName = (string) ($doc['storedFileName'] ?? '');
$filePath = $storedFileName !== '' ? hc_document_storage_dir() . '/' . $storedFileName : '';
if ($storedFileName === '' || !is_file($filePath)) {
    http_response_code(404);
    echo 'This document\'s file is no longer available.';
    exit;
}

$ext = strtolower(pathinfo($storedFileName, PATHINFO_EXTENSION));
$downloadName = (string) ($doc['name'] ?? ('document.' . $ext));
$disposition = (($_GET['view'] ?? '') === '1') ? 'inline' : 'attachment';

header('Content-Type: ' . hc_document_mime_for_extension($ext));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
