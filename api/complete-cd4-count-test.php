<?php
/**
 * =====================================================================
 * Complete CD4 Count Test (api/complete-cd4-count-test.php)
 * ---------------------------------------------------------------------
 * Called by medical_technician/cd4_count_form.php's Save Result
 * button. Writes the submitted numeric result onto this patient's
 * actual pending "CD4" lab_results doc (looked up server-side via
 * hc_get_cd4_count_docs_for_patient() — never a client-supplied
 * docId), auto-classifies it against the fixed 850-1150 c/uL reference
 * range (hc_cd4_reference_range()) as Low/Normal/High, and flips it to
 * "completed". A doc that's already "completed" is left alone — this
 * endpoint only ever fills in a result once, matching the form only
 * offering an editable input for a still-pending doc.
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
$rawResult = trim((string) ($body['result'] ?? ''));

if ($patientId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing patient id.']);
    exit;
}
if ($rawResult === '' || !is_numeric($rawResult) || (float) $rawResult < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Result must be a valid non-negative number.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

$pendingDoc = null;
foreach (hc_get_cd4_count_docs_for_patient($patientId) as $doc) {
    if (($doc['fields']['status'] ?? '') === 'pending') {
        $pendingDoc = $doc;
        break;
    }
}
if ($pendingDoc === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No pending CD4 Count order found for this patient.']);
    exit;
}

$value = (float) $rawResult;
$range = hc_cd4_reference_range();
$interpretation = $value < $range['low'] ? 'Low' : ($value > $range['high'] ? 'High' : 'Normal');
$badgeColor = match ($interpretation) {
    'Low' => '#D93025',
    'High' => '#E8710A',
    default => '#1E8E3E',
};

$existing = $pendingDoc['fields'];
$saved = $firestore->setDocument('lab_results', $pendingDoc['id'], array_merge($existing, [
    'status' => 'completed',
    'completedDate' => date('Y-m-d'),
    'completedTime' => date('g:i A'),
    'specimenType' => hc_cd4_specimen_label(),
    'interpretation' => 'CD4 Count: ' . $rawResult . ' c/uL (' . $interpretation . ', reference ' . $range['label'] . ').',
    'resultBadgeLabel' => $interpretation,
    'resultBadgeColor' => $badgeColor,
    'resultGroups' => [
        'group1_name' => 'CD4 Count',
        'group1_rows' => [[
            'test' => 'CD4 Count',
            'result' => $rawResult,
            'unit' => 'c/uL',
            'range' => $range['label'],
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
    'Entered CD4 Count result (' . $rawResult . ' c/uL, ' . $interpretation . ') for ' . $patient['name'] . '.'
);
hc_record_patient_history($patientId, 'laboratory_result', 'added', 'Entered CD4 Count result (' . $rawResult . ' c/uL, ' . $interpretation . ').');
hc_record_patient_activity($patientId, 'CD4 Count result entered.');

echo json_encode(['success' => true]);
