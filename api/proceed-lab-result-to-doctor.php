<?php
/**
 * =====================================================================
 * Proceed Lab Result to Doctor (api/proceed-lab-result-to-doctor.php)
 * ---------------------------------------------------------------------
 * Called by the "Proceed to Doctor" button on all 6 result-entry pages
 * (serology_rapid_test_form.php, urinalysis_form.php, cd4_count_form.php,
 * gram_stain_form.php, and the 2 generic forms — lab_result_form.php,
 * xray_result_form.php — covering every other test/exam) once a result
 * has been saved.
 *
 * PRIMARY path — resume the ORIGINAL consultation: routing a patient
 * to Laboratory no longer ends that consultation (api/complete-consultation.php
 * keeps it "ongoing" instead), so the real consultation the doctor was
 * already in is usually still sitting there waiting. This just appends
 * the completed lab_results doc id(s) onto that SAME consultation's
 * labResultDocIds — the doctor picks it back up via consultations.php's
 * "Ongoing Consultation" card (or the exact URL they were already on),
 * where the "Lab Results" card now shows the real result. No new
 * consultation, no duplicate record for the same visit.
 *
 * FALLBACK path — only when the original consultation can't be resumed
 * (no sourceConsultationId on the doc at all — from before that field
 * existed — or it's no longer "ongoing"/assigned to the same doctor for
 * some other reason): creates a real NEW "consultations" doc
 * (api/create-consultation.php's exact write shape — status "pending",
 * consultationType "follow_up") instead, so the result is never
 * silently unreachable. Assigned back to the SAME doctor who originally
 * ordered the test either way (read from the real completed
 * lab_results doc's "orderedBy" field, never a client-supplied name).
 *
 * Every relevant completed lab_results doc for this patient+testType
 * gets "routedToDoctorAt"/"routedToDoctorName" stamped on it regardless
 * of which path ran — the 4 pages read that back to show "Already
 * routed" instead of the button again, so this can't silently
 * duplicate-route the same result twice.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/patients.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';
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
$testType = trim((string) ($body['testType'] ?? ''));
// Only used by the 2 generic testTypes below (lab_result_form.php /
// xray_result_form.php) — the other 4 find their one pending doc by
// patientId+testName instead, since a patient can only ever have one
// pending order for a given NAMED test at a time. A generic "other"
// lab test or an X-ray exam has no such guarantee (e.g. both CBC and
// Blood Typing could be pending at once), so only recordId
// disambiguates which doc this button is actually for.
$recordId = trim((string) ($body['recordId'] ?? ''));

$validTestTypes = ['serology', 'urinalysis', 'cd4', 'gram_stain', 'lab_generic', 'xray_generic'];
if ($patientId === '' || !in_array($testType, $validTestTypes, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid request.']);
    exit;
}
if (in_array($testType, ['lab_generic', 'xray_generic'], true) && $recordId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing record id.']);
    exit;
}

$patient = hc_get_patient_profile($patientId);
if ($patient === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Patient not found.']);
    exit;
}

// Gather this patient's real COMPLETED docs for the requested test type
// — never trust a client-supplied result/doctor name, read it straight
// off the saved doc(s). $resultCollection tracks which Firestore
// collection these docs actually live in — every case below is
// "lab_results" except xray_generic, which is the only testType that
// can ever point at an xray_results doc.
$testLabel = '';
$completedDocs = [];
$resultCollection = 'lab_results';
switch ($testType) {
    case 'serology':
        $testLabel = 'Serology (Rapid Immunochromatographic Tests)';
        foreach (hc_get_serology_rapid_docs_for_patient($patientId) as $doc) {
            if (($doc['fields']['status'] ?? '') === 'completed') {
                $completedDocs[] = $doc;
            }
        }
        break;
    case 'urinalysis':
        $testLabel = 'Urinalysis';
        foreach (hc_get_urinalysis_docs_for_patient($patientId) as $doc) {
            if (($doc['fields']['status'] ?? '') === 'completed') {
                $completedDocs[] = $doc;
                break; // newest completed only
            }
        }
        break;
    case 'cd4':
        $testLabel = 'CD4 Count';
        foreach (hc_get_cd4_count_docs_for_patient($patientId) as $doc) {
            if (($doc['fields']['status'] ?? '') === 'completed') {
                $completedDocs[] = $doc;
                break;
            }
        }
        break;
    case 'gram_stain':
        $testLabel = 'Gram Stain';
        foreach (hc_get_gram_stain_docs_for_patient($patientId) as $doc) {
            if (($doc['fields']['status'] ?? '') === 'completed') {
                $completedDocs[] = $doc;
                break;
            }
        }
        break;
    case 'lab_generic':
        $testLabel = 'Laboratory Result';
        foreach (hc_get_lab_result_docs_for_patient($patientId) as $doc) {
            if ($doc['id'] === $recordId && ($doc['fields']['status'] ?? '') === 'completed') {
                $testLabel = $doc['fields']['testName'] ?? $testLabel;
                $completedDocs[] = $doc;
                break;
            }
        }
        break;
    case 'xray_generic':
        $resultCollection = 'xray_results';
        $testLabel = 'X-ray Result';
        foreach (hc_get_xray_result_docs_for_patient($patientId) as $doc) {
            if ($doc['id'] === $recordId && ($doc['fields']['status'] ?? '') === 'completed') {
                $testLabel = $doc['fields']['examName'] ?? $testLabel;
                $completedDocs[] = $doc;
                break;
            }
        }
        break;
}

if (count($completedDocs) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No completed ' . $testLabel . ' result found to proceed with.']);
    exit;
}

$assignedPersonnel = trim((string) ($completedDocs[0]['fields']['orderedBy'] ?? ''));
if ($assignedPersonnel === '' || $assignedPersonnel === 'Not specified') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'The ordering doctor could not be determined for this result.']);
    exit;
}

$resultSummary = implode('; ', array_map(
    fn ($doc) => $resultCollection === 'xray_results'
        ? ($doc['fields']['examName'] ?? '') . ': ' . ($doc['fields']['impression'] ?? '')
        : ($doc['fields']['testName'] ?? '') . ': ' . ($doc['fields']['resultBadgeLabel'] ?? ''),
    $completedDocs
));

// sourceConsultationId is stamped on every lab_results doc
// api/complete-consultation.php creates — the real consultation this
// test was ordered from, still sitting "ongoing" in the normal case
// (routing to Laboratory no longer ends it). Older docs from before
// that field existed have none.
$sourceConsultationId = trim((string) ($completedDocs[0]['fields']['sourceConsultationId'] ?? ''));
$sourceConsultation = $sourceConsultationId !== '' ? hc_get_consultation_by_id($sourceConsultationId) : null;
$canResumeOriginal = $sourceConsultation !== null
    && $sourceConsultation['status'] === 'ongoing'
    && $sourceConsultation['assignedPersonnel'] === $assignedPersonnel;

$labResultIds = array_map(fn ($doc) => $doc['id'], $completedDocs);
$resultLabel = null; // consultationCode shown back to the medtech either way

if ($canResumeOriginal) {
    // PRIMARY path — append onto the original consultation's own
    // labResultDocIds (merging with whatever's already there, e.g. a
    // patient with two separate lab orders routed back one at a time)
    // rather than creating a second record for the same visit.
    $existingLabResultIds = $sourceConsultation['labResultDocIds'] ?? [];
    $mergedLabResultIds = array_values(array_unique(array_merge($existingLabResultIds, $labResultIds)));

    $rawConsultDoc = $firestore->getDocument('consultations', $sourceConsultationId);
    $saved = $rawConsultDoc !== null && $firestore->setDocument('consultations', $sourceConsultationId, array_merge($rawConsultDoc, [
        'labResultDocIds' => $mergedLabResultIds,
    ]));

    if (!$saved) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not update the consultation. Please try again.']);
        exit;
    }

    $resultLabel = $sourceConsultation['consultationCode'];

    write_audit_log(
        'updated',
        'Consultations',
        'Attached ' . $testLabel . ' result (' . $resultSummary . ') to ' . $assignedPersonnel . '\'s ongoing consultation ' . $resultLabel . ' for "' . $patient['name'] . '".'
    );
} else {
    // FALLBACK path — the original consultation can't be resumed
    // (never linked, already ended some other way, or reassigned) —
    // same write shape api/create-consultation.php uses, so the result
    // is never silently unreachable.
    $chiefComplaint = $sourceConsultation['chiefComplaint'] ?? ($testLabel . ' result ready for review (' . $resultSummary . ').');

    $allConsultations = hc_get_all_consultations();
    $consultationCode = hc_next_consultation_code($allConsultations);

    $newId = $firestore->addDocument('consultations', [
        'consultationCode' => $consultationCode,
        'patientId' => $patientId,
        'patientName' => $patient['name'],
        'consultationType' => 'follow_up',
        'dateTime' => date('c'),
        'assignedPersonnel' => $assignedPersonnel,
        'chiefComplaint' => $chiefComplaint,
        'priority' => null,
        'nextFollowUp' => null,
        'notes' => 'Routed by Medical Technologist ' . ($_SESSION['name'] ?? '') . ' after completing ' . $testLabel . ' (' . $resultSummary . ').',
        'status' => 'pending',
        'createdAt' => date('c'),
        'createdBy' => $_SESSION['uid'] ?? '',
        'labResultDocIds' => $labResultIds,
    ]);

    if ($newId === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not create the follow-up consultation. Please try again.']);
        exit;
    }

    $resultLabel = $consultationCode;

    write_audit_log(
        'created',
        'Consultations',
        'Routed "' . $patient['name'] . '" to ' . $assignedPersonnel . ' for medication review after ' . $testLabel . ' result (' . $consultationCode . ').'
    );
}

$routedAt = date('c');
foreach ($completedDocs as $doc) {
    $firestore->setDocument($resultCollection, $doc['id'], array_merge($doc['fields'], [
        'routedToDoctorAt' => $routedAt,
        'routedToDoctorName' => $assignedPersonnel,
    ]));
}

hc_record_patient_history($patientId, 'laboratory_result', 'updated', 'Routed ' . $testLabel . ' result to ' . $assignedPersonnel . ' for review (consultation ' . $resultLabel . ').');
hc_record_patient_activity($patientId, $testLabel . ' result routed to ' . $assignedPersonnel . '.');

echo json_encode(['success' => true, 'consultationCode' => $resultLabel, 'assignedPersonnel' => $assignedPersonnel]);
