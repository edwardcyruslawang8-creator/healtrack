<?php
/**
 * =====================================================================
 * Complete Gram Stain Test (api/complete-gram-stain-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/gram_stain_form.php's Save Result
 * button. Writes the submitted Specimen/Result text onto this
 * patient's actual pending "Gram Stain" lab_results doc (looked up
 * server-side via hc_get_gram_stain_docs_for_patient() — never a
 * client-supplied docId) and flips it to "completed". Unlike CD4
 * Count, there's no numeric range to auto-classify against — a Gram
 * stain read is a free-text finding, so resultBadgeLabel is just
 * "Completed" once both fields are filled in. A doc that's already
 * "completed" is left alone — this endpoint only ever fills in a
 * result once, matching the form only offering editable inputs for a
 * still-pending doc.
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
$specimen = trim((string) ($body['specimen'] ?? ''));
$result = trim((string) ($body['result'] ?? ''));

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}
if ($specimen === '' || $result === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Both Specimen and Result are required.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_gram_stain_docs_for_patient($patientId) as $doc) {
    if (($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending Gram Stain order found for this patient.']);
    exit;
}

$existing = $pendingDoc['fields'];
$saved = $firestore->setDocument('lab_results', $pendingDoc['id'], array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'specimenType' => $specimen,
    'interpretation' => $result,
    'resultBadgeLabel' => 'Completed',
    'resultBadgeColor' => '#1E8E3E',
    'resultGroups' => [
        'group1_name' => 'Gram Stain',
        'group1_rows' => [[
            'test' => 'Gram Stain',
            'result' => $result,
            'unit' => '',
            'range' => '',
            'interpretation' => '',
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
// result actually shows up on the patient's Medical Records tab
// (doctor/medical-staff/subadmin patient_profile.php's "Laboratory
// Results" category) — previously nothing wrote one for a completed
// lab test, only for a completed consultation.
$firestore->addDocument('medical_records', [
    'patientId' => $patientId,
    'type' => 'laboratory',
    'title' => 'Laboratory — ' . $existing['testName'],
    'date' => date('Y-m-d'),
    'time' => date('g:i A'),
    'provider' => $_SESSION['name'] ?? null,
    'facility' => $existing['facility'] ?? 'Not specified',
]);

write_audit_log(
    'updated',
    'Laboratory & X-ray',
    'Entered Gram Stain result for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered Gram Stain result.');
hc_record_patient_activity($patientId, 'Gram Stain result entered.');

echo json_encode(['success' => true]);
