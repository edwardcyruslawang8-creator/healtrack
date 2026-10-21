<?php
/**
 * =====================================================================
 * Complete Consultation (api/complete-consultation.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/active_consultation.js when a doctor clicks
 * "Save & End Consultation" (or, when routing is "Laboratory"/"X-ray",
 * the SAME button relabeled "Send to Medical Technologist"/"Send to
 * Radiologic Technologist") on doctor/active_consultation.php — the one
 * endpoint that persists everything that page captures (History of
 * Present Illness, Review of Symptoms, Diagnosis, Prescription,
 * this-visit Vitals, Medications/Vaccines given, "Patient Will Be Next
 * In" routing, doctor's own Additional Notes — see
 * includes/firestore/consultations.php's hc_map_consultation_doc()
 * for the full new-field list) onto the real consultations/{id} doc.
 *
 * Status only flips to "completed" for every OTHER routing (or none).
 * Routing to Laboratory/X-ray does NOT end the consultation — the
 * doctor still owes this patient a real visit once results are back
 * (either by resuming this SAME still-"ongoing" consultation later via
 * consultations.php's "Ongoing Consultation" card, or via the real
 * follow-up consultation api/proceed-lab-result-to-doctor.php creates
 * once a medical/radiologic technologist finishes a test) — see the
 * Laboratory/X-ray routing blocks below.
 *
 * Only the doctor this consultation is actually assigned to (by real
 * name match, same limitation as every other doctor/*.php page) may
 * save it, and only while it's genuinely "ongoing" — matches the
 * same guard doctor/active_consultation.php itself enforces on page
 * load, re-checked here since this is the real write boundary. Since
 * a Laboratory/X-ray save keeps it "ongoing", the doctor can call this
 * endpoint again later on the same consultation (e.g. to actually end
 * it once they're done, or to change routing) — not a one-shot action
 * the way completing it is.
 *
 * Each Medications-type row (not Vaccines — no matching
 * vaccine-administration collection exists to extend safely) ALSO gets
 * written as a real medications/{new-id} doc, in the exact shape
 * api/create-patient.php:287-301 already writes, so it shows up on
 * this patient's ongoing Medications list immediately — not just
 * buried inside this one consultation's own record.
 *
 * "Patient Will Be Next In" = Laboratory/X-ray ALSO creates one real
 * pending lab_results/xray_results/{new-id} doc PER selected test/exam
 * (lab_results' first write path, previously seed/read-only; the FIRST
 * write path xray_results has ever had at all — every xray_results doc
 * before this was seed data, never created by any real flow), chosen
 * from doctor/active_consultation.php's "Select Laboratory Tests"/
 * "Select X-ray Exams" checkbox panel and validated here against the
 * same fixed catalogs (hc_lab_test_catalog()/hc_xray_exam_catalog())
 * rather than trusted as free text. Each one also carries a real
 * assignedToUid/assignedToName — see $assignedToUid's own resolution
 * below for why that's re-validated server-side rather than trusted
 * from the client. Every field hc_resolve_lab_result()/
 * hc_resolve_xray_result() reads is written on each one, not just the
 * ones a "pending" row's queue view shows — those functions have no
 * "??" fallback on most fields, so a technician opening an order's
 * detail page before completing it would otherwise hit undefined-key
 * warnings. hc_get_pending_lab_xray_notifications()
 * (config/constants.php's hc_medtech_unread_notification_count()/
 * hc_radiotech_unread_notification_count()) picks up any "pending" doc
 * automatically — no separate notification write needed here.
 *
 * Whenever the consultation genuinely ends (every routing except
 * Laboratory/X-ray), it ALSO writes one real medical_records/{new-id}
 * doc (type "consultation") and refreshes patients/{id}'s own
 * "lastVisit" field — see the block right before the Laboratory
 * routing block below for why those two were previously always
 * dead/blank.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/consultations.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php'; // hc_lab_test_catalog()
require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_xray_exam_catalog(), hc_get_xray_result_docs_for_patient()
require_once __DIR__ . '/../includes/firestore/accounts.php'; // hc_get_active_staff_by_role()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/shared/vitals_validation.php'; // hc_validate_vitals()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'doctor') {
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
$id = trim((string) ($body['id'] ?? ''));

if ($id === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing consultation id.']);
    exit;
}

$existing = $firestore->getDocument('consultations', $id);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Consultation not found.']);
    exit;
}
if (($existing['assignedPersonnel'] ?? '') !== ($_SESSION['name'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This consultation is not assigned to you.']);
    exit;
}
if (($existing['status'] ?? '') !== 'ongoing') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Only an ongoing consultation can be completed.']);
    exit;
}

// Free-text fields — trimmed, stored null when blank (same convention
// as every other optional field elsewhere in this app).
$historyOfPresentIllness = trim((string) ($body['historyOfPresentIllness'] ?? ''));
$reviewOfSymptoms = trim((string) ($body['reviewOfSymptoms'] ?? ''));
$diagnosis = trim((string) ($body['diagnosis'] ?? ''));
$doctorNotes = trim((string) ($body['doctorNotes'] ?? ''));

// Prescription comes from a contenteditable rich-text editor — allow
// only the formatting tags its own toolbar can actually produce
// (bold/italic/underline/lists/links), stripping everything else
// (script tags, iframes, etc.) as a defense-in-depth measure against a
// pasted-in malicious payload. strip_tags() alone would remove a
// <script> TAG but leave its inner text behind as plain text, so
// script/style blocks (tag + content) are removed first. This still
// doesn't sanitize attributes on the tags it keeps, so it's not a full
// HTML sanitizer, but it removes the primary script-injection vector
// for a page with no dedicated sanitizer library available.
$prescriptionHtml = trim((string) ($body['prescriptionHtml'] ?? ''));
if ($prescriptionHtml !== '') {
    $prescriptionHtml = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $prescriptionHtml);
    $prescriptionHtml = strip_tags($prescriptionHtml, '<b><strong><i><em><u><ul><ol><li><br><a><p><div><span>');
}

// "Needs a follow-up visit" is a real, separate field — independent of
// nextRouting below (which picks at most ONE of Laboratory/Pharmacy/
// Vaccination) — since needing a follow-up scheduled doesn't rule out
// also being routed somewhere today.
$needsFollowUp = (bool) ($body['needsFollowUp'] ?? false);

$validRouting = ['laboratory', 'xray', 'pharmacy', 'vaccination'];
$nextRouting = trim((string) ($body['nextRouting'] ?? ''));
if ($nextRouting !== '' && !in_array($nextRouting, $validRouting, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid routing selection.']);
    exit;
}

// Lab test names are validated against the real catalog (not trusted
// as free text) — a flat testName -> category map, so each test's real
// category can be looked up below rather than trusting a
// client-supplied one.
$testCategoryByName = [];
foreach (hc_lab_test_catalog() as $category => $tests) {
    foreach ($tests as $test) {
        $testCategoryByName[$test] = $category;
    }
}
$rawLabTests = is_array($body['labTests'] ?? null) ? $body['labTests'] : [];
$labTests = array_values(array_unique(array_filter(array_map(
    fn ($t) => trim((string) $t),
    $rawLabTests
), fn ($t) => isset($testCategoryByName[$t]))));

if ($nextRouting === 'laboratory' && count($labTests) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select at least one laboratory test.']);
    exit;
}

// X-ray exam names are validated against the real catalog the same way
// lab tests are above — a flat examName -> bodyPart map.
$bodyPartByExamName = hc_xray_exam_catalog();
$rawXrayExams = is_array($body['xrayExams'] ?? null) ? $body['xrayExams'] : [];
$xrayExams = array_values(array_unique(array_filter(array_map(
    fn ($t) => trim((string) $t),
    $rawXrayExams
), fn ($t) => isset($bodyPartByExamName[$t]))));

if ($nextRouting === 'xray' && count($xrayExams) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select at least one X-ray exam.']);
    exit;
}

// Vitals — every sub-field optional (a doctor can leave one blank if
// it genuinely wasn't retaken), but WHEN a value is actually given it's
// held to the same real clinical range this app enforces at intake
// (includes/medical-staff/add_patient_modal.php / api/create-patient.php).
// Only temperature/BP/heart-rate are doctor-editable on this page
// (doctor/active_consultation.php) — the doctor's own form never
// submits weight/height/bmi (read-only there, set by medical staff at
// check-in via api/create-consultation.php's own "Vital Signs"
// section), so those three are carried over from whatever this
// consultation already has instead of being blanked out by a save that
// never touched them.
$rawVitals = is_array($body['vitals'] ?? null) ? $body['vitals'] : [];
$existingVitals = is_array($existing['vitals'] ?? null) ? $existing['vitals'] : [];
$vitals = [
    'temperature' => trim((string) ($rawVitals['temperature'] ?? '')),
    'bloodPressureSystolic' => trim((string) ($rawVitals['bloodPressureSystolic'] ?? '')),
    'bloodPressureDiastolic' => trim((string) ($rawVitals['bloodPressureDiastolic'] ?? '')),
    'heartRate' => trim((string) ($rawVitals['heartRate'] ?? '')),
    'weight' => trim((string) ($existingVitals['weight'] ?? '')),
    'height' => trim((string) ($existingVitals['height'] ?? '')),
    'bmi' => trim((string) ($existingVitals['bmi'] ?? '')),
];

$vitalsError = hc_validate_vitals($vitals, false);
if ($vitalsError !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $vitalsError]);
    exit;
}

// Medications & Vaccines — skip blank-name rows (a stray empty row
// left over from the table UI, same tolerance
// api/create-stock-request.php's item rows already have).
$rawMedications = is_array($body['medications'] ?? null) ? $body['medications'] : [];
$medications = [];
foreach ($rawMedications as $m) {
    $name = trim((string) ($m['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $type = ($m['type'] ?? '') === 'vaccine' ? 'vaccine' : 'medication';
    $medications[] = [
        'type' => $type,
        'name' => $name,
        'dosage' => trim((string) ($m['dosage'] ?? '')),
        'frequency' => trim((string) ($m['frequency'] ?? '')),
        'duration' => trim((string) ($m['duration'] ?? '')),
        'route' => trim((string) ($m['route'] ?? '')),
        'instructions' => trim((string) ($m['instructions'] ?? '')),
    ];
}

if ($nextRouting === 'pharmacy' && count($medications) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please add at least one medicine or vaccine before sending this patient to the Pharmacy.']);
    exit;
}

$updated = $existing;
// "Laboratory"/"X-ray" both keep the consultation "ongoing" instead of
// completing it — see this file's header comment for why. Every other
// routing (or none selected) completes it exactly as before.
if (!in_array($nextRouting, ['laboratory', 'xray'], true)) {
    $updated['status'] = 'completed';
    $updated['endedAt'] = date('c');
}
$updated['historyOfPresentIllness'] = $historyOfPresentIllness !== '' ? $historyOfPresentIllness : null;
$updated['reviewOfSymptoms'] = $reviewOfSymptoms !== '' ? $reviewOfSymptoms : null;
$updated['diagnosis'] = $diagnosis !== '' ? $diagnosis : null;
$updated['prescriptionHtml'] = $prescriptionHtml !== '' ? $prescriptionHtml : null;
$updated['vitals'] = $vitals;
$updated['medications'] = $medications;
$updated['nextRouting'] = $nextRouting !== '' ? $nextRouting : null;
$updated['needsFollowUp'] = $needsFollowUp;
$updated['labTests'] = $labTests;
$updated['xrayExams'] = $xrayExams;
$updated['doctorNotes'] = $doctorNotes !== '' ? $doctorNotes : null;

$saved = $firestore->setDocument('consultations', $id, $updated);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the consultation. Please try again.']);
    exit;
}

// Each medication-type row also becomes a real medications/{id} doc,
// in the exact shape api/create-patient.php:287-301 already writes —
// so it shows up on this patient's ongoing Medications list right
// away, not just inside this one consultation's own record.
$indication = $diagnosis !== '' ? $diagnosis : (($existing['chiefComplaint'] ?? '') !== '' ? $existing['chiefComplaint'] : 'Not specified');
foreach ($medications as $m) {
    if ($m['type'] !== 'medication') {
        continue;
    }
    $firestore->addDocument('medications', [
        'patientId' => $existing['patientId'] ?? '',
        'name' => $m['name'],
        'category' => 'General',
        'dosage' => $m['dosage'] !== '' ? $m['dosage'] : 'Not specified',
        'frequency' => $m['frequency'] !== '' ? $m['frequency'] : 'Not specified',
        'route' => $m['route'] !== '' ? $m['route'] : 'Not specified',
        'indication' => $indication,
        'startDate' => date('Y-m-d'),
        'endDate' => null,
        'endDateNote' => null,
        'prescribedBy' => $_SESSION['name'] ?? 'Not specified',
        'notes' => 'Prescribed during consultation ' . ($existing['consultationCode'] ?? $id) . ' on ' . date('M j, Y') . '.',
        'status' => 'active',
    ]);
}

// "Laboratory" routing -> one real pending lab_results doc PER
// selected test (not one doc listing several tests — matches how a
// real lab queue tracks orders, and how medical_technician/laboratory_xray.php's
// table already renders one row per doc). Every field
// hc_resolve_lab_result() reads is set on each (not just the
// "pending"-view ones) — see this file's header comment for why.
// resultGroups is the one field that already has a "??" fallback
// everywhere it's read, so an empty array is fine there. testType is
// each test's real category (looked up from $testCategoryByName, built
// from hc_lab_test_catalog() above), not a client-supplied value.
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$doctorFacility = $myDoc['facility'] ?? 'North Super Health Center';

// Staff picker — "who should this order actually go to"
// (doctor/active_consultation.php's Laboratory/X-ray "Assign To"
// picker). The client-supplied uid is re-validated here against the
// SAME real active-staff list the picker was built from
// (hc_get_active_staff_by_role()) — never trusted as-is, since a
// crafted request could otherwise assign an order to any arbitrary uid
// (wrong role, wrong facility, inactive account). Resolves to null (no
// assignment — stays in the general queue, same as before this
// existed) if the submitted uid doesn't match a real, currently-active
// account of the right role at this doctor's own facility.
$assignedToUid = trim((string) ($body['assignedToUid'] ?? ''));
$assignedToName = null;
if ($assignedToUid !== '' && in_array($nextRouting, ['laboratory', 'xray'], true)) {
    $assignRole = $nextRouting === 'laboratory' ? 'medical_technician' : 'radio_technologist';
    foreach (hc_get_active_staff_by_role($assignRole, $doctorFacility) as $staff) {
        if ($staff['uid'] === $assignedToUid) {
            $assignedToName = $staff['name'];
            break;
        }
    }
    if ($assignedToName === null) {
        $assignedToUid = '';
    }
}

// Consultation genuinely ending (not routed to Laboratory/X-ray, which
// keeps it "ongoing") -> a real medical_records/{new-id} doc, type
// "consultation", so this patient's Profile > Overview "Latest
// Consultation" card (doctor/medical-staff/subadmin patient_profile.php,
// via hc_get_patient_overview()) actually reflects it — previously
// nothing ever wrote to this collection, so that card was permanently
// stuck on seed data or the "No consultations on record." empty state.
// Also refreshes patients/{id}'s own "lastVisit" field, read by every
// Patient Profile header card's "Last Visit" field but, likewise, never
// written anywhere until now.
if (!in_array($nextRouting, ['laboratory', 'xray'], true)) {
    $completionDate = date('Y-m-d');
    $completionTime = date('g:i A');
    $chiefComplaint = $existing['chiefComplaint'] ?? '';

    $firestore->addDocument('medical_records', [
        'patientId' => $existing['patientId'] ?? '',
        'type' => 'consultation',
        'title' => 'Consultation — ' . ($chiefComplaint !== '' ? $chiefComplaint : 'General Consultation'),
        'date' => $completionDate,
        'time' => $completionTime,
        'provider' => $_SESSION['name'] ?? 'Not specified',
        'facility' => $doctorFacility,
        'chiefComplaint' => $chiefComplaint !== '' ? $chiefComplaint : null,
        'diagnosis' => $diagnosis !== '' ? $diagnosis : null,
        'vitals' => $vitals,
    ]);

    $patientId = $existing['patientId'] ?? '';
    $patientDoc = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
    if ($patientDoc !== null) {
        $firestore->setDocument('patients', $patientId, array_merge($patientDoc, [
            'lastVisit' => $completionDate,
        ]));
    }
}

if ($nextRouting === 'laboratory' && count($labTests) > 0) {
    $orderedDate = date('Y-m-d');
    $orderedTime = date('g:i A');

    // Tests THIS consultation already has a real lab_results doc for
    // (sourceConsultationId match, any status) get skipped — the
    // doctor's own checkbox grid locks these already
    // (doctor/active_consultation.php), this is the server-side
    // backstop against a duplicate order for the same test slipping
    // through (e.g. a resubmit, or the checkbox being force-enabled
    // client-side) — this is exactly how a real duplicate Gram Stain
    // order happened on a live consultation before this existed.
    $alreadyOrderedTestNames = [];
    foreach (hc_get_lab_result_docs_for_patient($existing['patientId'] ?? '') as $doc) {
        if (($doc['fields']['sourceConsultationId'] ?? '') === $id) {
            $alreadyOrderedTestNames[] = $doc['fields']['testName'] ?? '';
        }
    }

    foreach ($labTests as $testName) {
        if (in_array($testName, $alreadyOrderedTestNames, true)) {
            continue;
        }
        $firestore->addDocument('lab_results', [
            'patientId' => $existing['patientId'] ?? '',
            'testName' => $testName,
            'testType' => $testCategoryByName[$testName] ?? 'Other',
            'orderedBy' => $_SESSION['name'] ?? 'Not specified',
            'orderedDate' => $orderedDate,
            'orderedTime' => $orderedTime,
            'completedDate' => '',
            'completedTime' => '',
            'facility' => $doctorFacility,
            'status' => 'pending',
            'specimenType' => 'Not specified',
            'remarks' => null,
            'clinicalNotes' => 'Ordered during consultation ' . ($existing['consultationCode'] ?? $id) . '.',
            // The real consultations/{id} doc this test was ordered from
            // — api/proceed-lab-result-to-doctor.php reads this back to
            // reuse the SAME chief complaint on the follow-up
            // consultation it creates once a result is ready, rather
            // than inventing a new one.
            'sourceConsultationId' => $id,
            'attachedFileName' => null,
            'attachedFileSize' => null,
            'resultBadgeLabel' => 'Pending',
            'resultBadgeColor' => '#B45309',
            'interpretation' => '',
            'resultGroups' => [],
            // Real "who should do this" — see $assignedToUid's own
            // resolution above. Null (not assigned) is a normal,
            // expected value: the order simply stays in the shared
            // medical-technician queue for anyone to pick up, same
            // behavior this app had before staff assignment existed.
            'assignedToUid' => $nextRouting === 'laboratory' && $assignedToUid !== '' ? $assignedToUid : null,
            'assignedToName' => $nextRouting === 'laboratory' ? $assignedToName : null,
        ]);
    }
}

// "X-ray" routing -> one real pending xray_results doc PER selected
// exam — same shape/reasoning as the "Laboratory" block above, just
// examName/bodyPart (hc_xray_exam_catalog()) instead of testName/
// testType, and no resultGroups/interpretation (xray_results has never
// modeled those — see includes/firestore/xray_results.php's
// hc_resolve_xray_result()).
if ($nextRouting === 'xray' && count($xrayExams) > 0) {
    $orderedDate = date('Y-m-d');
    $orderedTime = date('g:i A');

    // Same server-side duplicate-order backstop as Laboratory above.
    $alreadyOrderedExamNames = [];
    foreach (hc_get_xray_result_docs_for_patient($existing['patientId'] ?? '') as $doc) {
        if (($doc['fields']['sourceConsultationId'] ?? '') === $id) {
            $alreadyOrderedExamNames[] = $doc['fields']['examName'] ?? '';
        }
    }

    foreach ($xrayExams as $examName) {
        if (in_array($examName, $alreadyOrderedExamNames, true)) {
            continue;
        }
        $firestore->addDocument('xray_results', [
            'patientId' => $existing['patientId'] ?? '',
            'examName' => $examName,
            'bodyPart' => $bodyPartByExamName[$examName] ?? 'Not specified',
            'orderedBy' => $_SESSION['name'] ?? 'Not specified',
            'orderedDate' => $orderedDate,
            'orderedTime' => $orderedTime,
            'completedDate' => '',
            'completedTime' => '',
            'facility' => $doctorFacility,
            'status' => 'pending',
            'sourceConsultationId' => $id,
            'findings' => [],
            'impression' => '',
            'attachedFileName' => null,
            'attachedFileSize' => null,
            'assignedToUid' => $nextRouting === 'xray' && $assignedToUid !== '' ? $assignedToUid : null,
            'assignedToName' => $nextRouting === 'xray' ? $assignedToName : null,
        ]);
    }
}

// "Pharmacy" routing -> one real pending pharmacy_orders doc PER
// medicine/vaccine row entered in the Medications & Vaccines table
// (see includes/firestore/pharmacy_orders.php's header comment — the
// first write path that collection has ever had). Covers BOTH row
// types here (medications AND vaccines), unlike the "medications"
// collection write above which only ever persists medicine-type rows
// — a pharmacist dispenses either kind, so both need to reach their
// real queue (pharmacist/prescription_queue.php).
if ($nextRouting === 'pharmacy' && count($medications) > 0) {
    $orderedDate = date('Y-m-d');
    $orderedTime = date('g:i A');

    foreach ($medications as $m) {
        $firestore->addDocument('pharmacy_orders', [
            'patientId' => $existing['patientId'] ?? '',
            'patientName' => $existing['patientName'] ?? '',
            'itemName' => $m['name'],
            'itemType' => $m['type'],
            'dosage' => $m['dosage'] !== '' ? $m['dosage'] : 'Not specified',
            'frequency' => $m['frequency'] !== '' ? $m['frequency'] : 'Not specified',
            'duration' => $m['duration'] !== '' ? $m['duration'] : 'Not specified',
            'route' => $m['route'] !== '' ? $m['route'] : 'Not specified',
            'instructions' => $m['instructions'] !== '' ? $m['instructions'] : null,
            'prescribedBy' => $_SESSION['name'] ?? 'Not specified',
            'facility' => $doctorFacility,
            'orderedDate' => $orderedDate,
            'orderedTime' => $orderedTime,
            'status' => 'pending',
            'sourceConsultationId' => $id,
            'dispensedDate' => '',
            'dispensedTime' => '',
            'dispensedBy' => null,
            'notes' => 'Prescribed during consultation ' . ($existing['consultationCode'] ?? $id) . '.',
        ]);
    }
}

$historyPatientId = $existing['patientId'] ?? '';
$consultationLabel = $existing['consultationCode'] ?? $id;

if ($nextRouting === 'laboratory') {
    $assignedSuffix = $assignedToName !== null ? ' (assigned to ' . $assignedToName . ')' : '';
    write_audit_log(
        'updated',
        'Consultations',
        'Sent ' . ($existing['patientName'] ?? '') . ' to the laboratory for ' . count($labTests) . ' test(s)' . $assignedSuffix . ' — consultation ' . $consultationLabel . '.'
    );
    hc_record_patient_history($historyPatientId, 'medical_record', 'updated', 'Consultation ' . $consultationLabel . ' sent to the laboratory — ' . count($labTests) . ' test(s) ordered' . $assignedSuffix . '.');
    hc_record_patient_activity($historyPatientId, 'Sent to laboratory for ' . count($labTests) . ' test(s)' . $assignedSuffix . '.');
} elseif ($nextRouting === 'xray') {
    $assignedSuffix = $assignedToName !== null ? ' (assigned to ' . $assignedToName . ')' : '';
    write_audit_log(
        'updated',
        'Consultations',
        'Sent ' . ($existing['patientName'] ?? '') . ' for X-ray — ' . count($xrayExams) . ' exam(s)' . $assignedSuffix . ' — consultation ' . $consultationLabel . '.'
    );
    hc_record_patient_history($historyPatientId, 'medical_record', 'updated', 'Consultation ' . $consultationLabel . ' sent for X-ray — ' . count($xrayExams) . ' exam(s) ordered' . $assignedSuffix . '.');
    hc_record_patient_activity($historyPatientId, 'Sent for X-ray — ' . count($xrayExams) . ' exam(s)' . $assignedSuffix . '.');
} elseif ($nextRouting === 'pharmacy' && count($medications) > 0) {
    write_audit_log(
        'completed',
        'Consultations',
        'Completed the consultation for ' . ($existing['patientName'] ?? '') . ' (' . $consultationLabel . ') — sent to the pharmacy, ' . count($medications) . ' item(s) prescribed.'
    );
    hc_record_patient_history($historyPatientId, 'medical_record', 'updated', 'Consultation ' . $consultationLabel . ' completed — sent to the pharmacy, ' . count($medications) . ' item(s) prescribed.');
    hc_record_patient_activity($historyPatientId, 'Consultation completed; sent to pharmacy.');
} else {
    write_audit_log(
        'completed',
        'Consultations',
        'Completed the consultation for ' . ($existing['patientName'] ?? '') . ' (' . $consultationLabel . ').'
    );
    hc_record_patient_history($historyPatientId, 'medical_record', 'updated', 'Consultation ' . $consultationLabel . ' completed.');
    hc_record_patient_activity($historyPatientId, 'Consultation completed.');
}

echo json_encode(['success' => true]);
