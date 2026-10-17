<?php
/**
 * =====================================================================
 * Complete Urinalysis Test (api/complete-urinalysis-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/urinalysis_form.php's Save Results
 * button. Writes the submitted Physical/Microscopic Examination values
 * plus an overall Normal/Abnormal result onto this patient's actual
 * pending "Urinalysis" lab_results doc (looked up server-side via
 * hc_get_urinalysis_docs_for_patient() — never a client-supplied
 * docId) and flips it to "completed". Only the 14 real field keys
 * (hc_urinalysis_physical_fields()/hc_urinalysis_microscopic_fields())
 * and "Normal"/"Abnormal" are accepted; anything else is ignored. A
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
$rawValues = is_array($body['values'] ?? null) ? $body['values'] : [];
$overallResult = trim((string) ($body['overallResult'] ?? ''));

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}
if (!in_array($overallResult, ['Normal', 'Abnormal'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid overall result.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_urinalysis_docs_for_patient($patientId) as $doc) {
    if (($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending Urinalysis order found for this patient.']);
    exit;
}

$physicalFields = hc_urinalysis_physical_fields();
$microscopicFields = hc_urinalysis_microscopic_fields();

/** @param array<string,string> $fieldLabels */
$buildRows = function (array $fieldLabels) use ($rawValues): array {
    $rows = [];
    foreach ($fieldLabels as $key => $label) {
        $value = trim((string) ($rawValues[$key] ?? ''));
        $rows[] = ['test' => $label, 'result' => $value, 'unit' => '', 'range' => '', 'interpretation' => ''];
    }
    return $rows;
};

$existing = $pendingDoc['fields'];
$saved = $firestore->setDocument('lab_results', $pendingDoc['id'], array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'interpretation' => 'Urinalysis result: ' . $overallResult . '.',
    'resultBadgeLabel' => $overallResult,
    'resultBadgeColor' => $overallResult === 'Normal' ? '#1E8E3E' : '#D93025',
    'resultGroups' => [
        'group1_name' => 'Physical Examination',
        'group1_rows' => $buildRows($physicalFields),
        'group2_name' => 'Microscopic Examination',
        'group2_rows' => $buildRows($microscopicFields),
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
    'Entered Urinalysis result (' . $overallResult . ') for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered Urinalysis result (' . $overallResult . ').');
hc_record_patient_activity($patientId, 'Urinalysis result entered.');

echo json_encode(['success' => true]);
