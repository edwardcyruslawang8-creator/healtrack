<?php
/**
 * =====================================================================
 * Complete X-ray Result (api/complete-xray-result.php)
 * ---------------------------------------------------------------------
 * Called by radio_technologist/xray_result_form.php's Save Result
 * button. Writes the submitted Findings/Impression AND a real uploaded
 * result document (scanned report/exported image — see
 * includes/shared/xray_result_storage.php's header comment for why this
 * lives on local disk, same pattern as api/upload-document.php) onto
 * the EXACT xray_results doc identified by &recordId= and flips it to
 * "completed" — same field shape (findings/impression)
 * laboratory_result_details.php's "X-ray Findings" card already
 * renders. A doc that's already "completed" is left alone — this
 * endpoint only ever fills in a result once, matching the form only
 * offering editable inputs for a still-pending doc.
 *
 * multipart/form-data, not JSON — carries a real file, every other
 * field (patientId/recordId/findings/impression) rides along as plain
 * POST fields rather than a JSON body (switched from JSON specifically
 * to add the file here).
 *
 * Also stamps completedByUid/completedByName — real per-technologist
 * attribution that xray_results never had before (every other field on
 * this doc is facility-wide/unattributed; see
 * includes/radio_technologist/radio_technologist_dashboard_firestore.php's
 * header comment for how the dashboard uses this going forward, scoped
 * only to exams completed from this point on — older docs stay
 * unattributed, there's no honest way to backfill who did them).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/xray_results.php';
require_once __DIR__ . '/../includes/shared/xray_result_storage.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['medical_technician', 'radio_technologist'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$patientId = trim((string) ($_POST['patientId'] ?? ''));
$recordId = trim((string) ($_POST['recordId'] ?? ''));
$findingsRaw = trim((string) ($_POST['findings'] ?? ''));
$impression = trim((string) ($_POST['impression'] ?? ''));

if ($patientId === '' || $recordId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient or record id.']);
    exit;
}
if ($findingsRaw === '' || $impression === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Both Findings and Impression are required.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_xray_result_docs_for_patient($patientId) as $doc) {
    if ($doc['id'] === $recordId && ($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending X-ray order found for this record.']);
    exit;
}

// ---------------------------------------------------------------------
// X-ray result document — required (the result isn't really "done"
// without the actual report/image on file). Same validation sequence
// as api/upload-document.php: real upload check, size ceiling,
// extension allow-list, then a real server-side MIME sniff so a
// renamed .php-with-a-.pdf-extension file still gets rejected.
// ---------------------------------------------------------------------
$file = $_FILES['resultFile'] ?? null;
if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please attach the X-ray result document.']);
    exit;
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $tooLarge = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $tooLarge ? 'File is too large. Maximum size is 10MB.' : 'The file could not be uploaded. Please try again.']);
    exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The file could not be uploaded. Please try again.']);
    exit;
}
if ($file['size'] > hc_xray_result_max_size_bytes()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 10MB.']);
    exit;
}

$originalName = trim((string) $file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$ext = $ext === 'jpeg' ? 'jpg' : $ext;
if (!in_array($ext, hc_xray_result_allowed_extensions(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Unsupported file type. Please upload a PDF, JPG, or PNG file.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$expectedMimes = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'png' => ['image/png']];
if (!in_array($realMime, $expectedMimes[$ext] ?? [], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'This file does not look like a valid ' . strtoupper($ext) . ' file.']);
    exit;
}

$storedFileName = bin2hex(random_bytes(16)) . '.' . $ext;
$destination = hc_xray_result_storage_dir() . '/' . $storedFileName;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the file. Please try again.']);
    exit;
}

$existing = $pendingDoc['fields'];
$examName = $existing['examName'] ?? 'X-ray Exam';

// One finding per line, blank lines dropped.
$findings = array_values(array_filter(array_map('trim', explode("\n", $findingsRaw)), fn ($line) => $line !== ''));

$saved = $firestore->setDocument('xray_results', $pendingDoc['id'], array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'findings' => $findings,
    'impression' => $impression,
    'completedByUid' => $_SESSION['uid'] ?? null,
    'completedByName' => $_SESSION['name'] ?? null,
    'attachedFileName' => $originalName,
    'attachedFileSize' => hc_format_file_size((int) $file['size']),
    'storedFileName' => $storedFileName,
]));

if (!$saved) {
    @unlink($destination); // don't leave an orphaned file if the Firestore write failed
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the result. Please try again.']);
    exit;
}

// A real medical_records/{new-id} doc (type "laboratory") so this
// result actually shows up on the patient's Medical Records tab — same
// convention as api/complete-gram-stain-test.php etc.
$firestore->addDocument('medical_records', [
    'patientId' => $patientId,
    'type' => 'laboratory',
    'title' => 'X-ray — ' . $examName,
    'date' => date('Y-m-d'),
    'time' => date('g:i A'),
    'provider' => $_SESSION['name'] ?? null,
    'facility' => $existing['facility'] ?? 'Not specified',
]);

write_audit_log(
    'updated',
    'Laboratory & X-ray',
    'Entered X-ray result (' . $examName . ') for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered X-ray result (' . $examName . ').');
hc_record_patient_activity($patientId, 'X-ray result entered (' . $examName . ').');

echo json_encode(['success' => true]);
