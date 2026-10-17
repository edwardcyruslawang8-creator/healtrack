<?php
/**
 * =====================================================================
 * Complete X-ray Result (api/complete-xray-result.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/xray_result_form.php's Save Result
 * button. Writes the submitted Findings/Impression onto the EXACT
 * xray_results doc identified by &recordId= and flips it to
 * "completed" — same field shape (findings/impression)
 * laboratory_result_details.php's "X-ray Findings" card already
 * renders. A doc that's already "completed" is left alone — this
 * endpoint only ever fills in a result once, matching the form only
 * offering editable inputs for a still-pending doc.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/xray_results.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$patientId = trim((string) ($body['patientId'] ?? ''));
$recordId = trim((string) ($body['recordId'] ?? ''));
$findingsRaw = trim((string) ($body['findings'] ?? ''));
$impression = trim((string) ($body['impression'] ?? ''));

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
]));

if (!$saved) {
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
