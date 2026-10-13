<?php
/**
 * =====================================================================
 * Complete Lab Result (api/complete-lab-result.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/lab_result_form.php's Save Result
 * button — the generic result-entry form for any lab test that isn't
 * one of the 4 tests with their own dedicated page (see that form's
 * header comment). Writes the submitted Specimen/Result/Unit/Range/
 * Interpretation/Remarks onto the EXACT lab_results doc identified by
 * &recordId= (never found by patientId+testName search like the 4
 * named endpoints — a patient can have several different pending
 * "other" tests at once, e.g. both CBC and Blood Typing, so only
 * recordId disambiguates which one this save is for). Flips it to
 * "completed", same field shape (resultGroups/interpretation/
 * resultBadgeLabel) laboratory_result_details.php already renders. A
 * doc that's already "completed" is left alone — this endpoint only
 * ever fills in a result once, matching the form only offering
 * editable inputs for a still-pending doc.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
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
$specimen = trim((string) ($body['specimen'] ?? ''));
$result = trim((string) ($body['result'] ?? ''));
$unit = trim((string) ($body['unit'] ?? ''));
$range = trim((string) ($body['range'] ?? ''));
$interpretation = trim((string) ($body['interpretation'] ?? ''));
$remarks = trim((string) ($body['remarks'] ?? ''));

if ($patientId === '' || $recordId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient or record id.']);
    exit;
}
if ($result === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Result is required.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_lab_result_docs_for_patient($patientId) as $doc) {
    if ($doc['id'] === $recordId && ($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending lab order found for this record.']);
    exit;
}

$existing = $pendingDoc['fields'];
$testName = $existing['testName'] ?? 'Lab Test';

$badgeLabel = $interpretation !== '' ? $interpretation : 'Completed';
$badgeColor = ($interpretation === '' || in_array(strtolower($interpretation), ['normal', 'negative'], true))
    ? '#1E8E3E'
    : '#D93025';

$summary = $testName . ': ' . $result . ($unit !== '' ? ' ' . $unit : '') . ($interpretation !== '' ? ' (' . $interpretation . ')' : '') . '.';
$panelText = $remarks !== '' ? $remarks : $summary;

$saved = $firestore->setDocument('lab_results', $pendingDoc['id'], array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'specimenType' => $specimen !== '' ? $specimen : 'Not specified',
    'remarks' => $remarks !== '' ? $remarks : null,
    'interpretation' => $panelText,
    'resultBadgeLabel' => $badgeLabel,
    'resultBadgeColor' => $badgeColor,
    'resultGroups' => [
        'group1_name' => $testName,
        'group1_rows' => [[
            'test' => $testName,
            'result' => $result,
            'unit' => $unit,
            'range' => $range,
            'interpretation' => $interpretation,
        ]],
        'group2_name' => null,
        'group2_rows' => [],
    ],
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
    'title' => 'Laboratory — ' . $testName,
    'date' => date('Y-m-d'),
    'time' => date('g:i A'),
    'provider' => $_SESSION['name'] ?? null,
    'facility' => $existing['facility'] ?? 'Not specified',
]);

write_audit_log(
    'updated',
    'Laboratory & X-ray',
    'Entered ' . $testName . ' result (' . $result . ($unit !== '' ? ' ' . $unit : '') . ') for "' . $patient['name'] . '".'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered ' . $testName . ' result (' . $result . ($unit !== '' ? ' ' . $unit : '') . ').');
hc_record_patient_activity($patientId, $testName . ' result entered.');

echo json_encode(['success' => true]);
