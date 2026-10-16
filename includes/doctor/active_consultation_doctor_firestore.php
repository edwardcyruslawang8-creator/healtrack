<?php
/**
 * =====================================================================
 * Active Consultation (Doctor) — Data
 * ---------------------------------------------------------------------
 * CONNECTED — doctor/active_consultation.php?id=<consultation id>, the
 * clinical documentation workspace a doctor lands on after "Proceed
 * with Consultation" in doctor/consultations.php's
 * #startConsultationModal (see assets/js/doctor_consultations.js).
 *
 * Loads the one real consultations/{id} doc
 * (hc_get_consultation_by_id(), includes/firestore/consultations.php)
 * plus the patient behind it (hc_get_patient_profile()) for the header
 * card. Guards: only the signed-in doctor's OWN consultation
 * (assignedPersonnel matches their name — same limitation as every
 * other doctor/*.php page) and only while it's actually "ongoing" —
 * anything else (wrong doctor, wrong id, already completed/cancelled/
 * still pending) redirects back to consultations.php rather than
 * showing a broken or out-of-place workspace.
 *
 * "Past Medical History" and "Allergies" ("from Front Desk" cards) are
 * REAL — hc_get_medical_conditions_for_patient() and
 * hc_get_allergies_for_patient() (includes/firestore/medical_records.php,
 * includes/firestore/allergies.php), the same real data every other
 * role's Allergies/Medical Records pages already show. Past Medical
 * History stays read-only here; Allergies is also WRITABLE — "+ Add
 * Allergy" POSTs api/add-allergy.php, the first write path the real
 * "allergies" collection has ever had outside of patient registration
 * (api/create-patient.php). A new allergy is a real, permanent,
 * patient-wide record the moment it's added — independent of whether
 * this consultation goes on to Save & End or Cancel.
 *
 * Everything else this page captures (History of Present Illness,
 * Review of Symptoms, Diagnosis, Prescription, Vital Signs for THIS
 * visit, Medications/Vaccines given, "Patient Will Be Next In"
 * routing, doctor's own Additional Notes) has no prior data model in
 * this app — see includes/firestore/consultations.php's
 * hc_map_consultation_doc() for the new (all-nullable) fields this
 * page introduces, written only on Save & End
 * (api/complete-consultation.php). Vitals specifically: this app only
 * ever captures vitals ONCE, at patient registration
 * (patients/{id}.intakeVitals — includes/firestore/patients.php has no
 * read-back for it, api/create-patient.php writes it) — that intake
 * snapshot is used here as a PREFILL starting point (temperature/blood
 * pressure/heart rate — the only vitals this app captures at all), the
 * doctor can adjust for this visit's real numbers.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/consultations.php';
require_once __DIR__ . '/../firestore/allergies.php';
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_medical_conditions_for_patient()
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items() — Medications/Vaccines name datalists
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_lab_test_catalog()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$myName = $_SESSION['name'] ?? 'Doctor';

$doctorProfile = [
    'name'     => $myName,
    'role'     => 'Doctor',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Load + guard — see this file's header comment for exactly what's
// enforced and why. api/complete-consultation.php enforces the same
// two checks again server-side; this is the page-load gate only.
// ---------------------------------------------------------------------
$consultationId = trim((string) ($_GET['id'] ?? ''));
$consultation = hc_get_consultation_by_id($consultationId);

if ($consultation === null || $consultation['assignedPersonnel'] !== $myName || $consultation['status'] !== 'ongoing') {
    header('Location: consultations.php');
    exit;
}

// A patient routed to the lab is physically there, not with the
// doctor — this workspace stays closed to them until every test
// ordered from this consultation is back (hc_consultation_is_awaiting_lab_results()).
// The queue card that links here already hides "Resume Consultation"
// for exactly this case (includes/doctor/_consultation_queue_cards.php);
// this is the same rule enforced again in case that link is ever
// reached directly (a stale tab, a bookmarked URL).
if (hc_consultation_is_awaiting_lab_results($consultation)) {
    header('Location: consultations.php?blocked=awaiting_lab');
    exit;
}

$patient = hc_get_patient_profile($consultation['patientId']) ?? hc_not_found_patient_profile($consultation['patientId']);

$consultationTypeMeta = hc_consultation_type_meta();
$consultationStatusMeta = hc_consultation_status_meta();
$consultationType = $consultationTypeMeta[$consultation['consultationType']] ?? $consultationTypeMeta['walk_in'];
$consultationStatus = $consultationStatusMeta[$consultation['status']] ?? $consultationStatusMeta['pending'];

// ---------------------------------------------------------------------
// "Past Medical History" / "Allergies" (from Front Desk) — real,
// read-only. Empty-state text matches this page's own framing rather
// than each source page's own generic "No records" wording.
// ---------------------------------------------------------------------
$medicalConditions = hc_get_medical_conditions_for_patient($patient['id']);
$allergies = hc_get_allergies_for_patient($patient['id']);

// ---------------------------------------------------------------------
// Vitals — prefill from the patient's own registration-time snapshot
// (real, but a one-time intake value, not "today"). Fetched as a raw
// doc read (not through hc_get_patient_profile(), whose fixed
// header-card shape doesn't include intakeVitals) since nothing else
// needs this.
// ---------------------------------------------------------------------
$rawPatientDoc = $firestore->getDocument('patients', $patient['id']);
$intakeVitals = $rawPatientDoc['intakeVitals'] ?? [];
$vitalsPrefill = [
    'temperature' => $intakeVitals['temperature'] ?? '',
    'bloodPressureSystolic' => $intakeVitals['bloodPressureSystolic'] ?? '',
    'bloodPressureDiastolic' => $intakeVitals['bloodPressureDiastolic'] ?? '',
    'heartRate' => $intakeVitals['heartRate'] ?? '',
    'weight' => $intakeVitals['weight'] ?? '',
    'height' => $intakeVitals['height'] ?? '',
    'bmi' => $intakeVitals['bmi'] ?? '',
];
// A consultation already carrying its OWN vitals — either taken by
// medical staff when they set this consultation
// (api/create-consultation.php's "Vital Signs" section, the normal
// case for a real visit) or already Saved by the doctor themselves on
// an "ongoing" consultation re-opened later — wins over the intake
// prefill, since that's this actual visit's reading rather than a
// one-time registration snapshot.
foreach ($consultation['vitals'] as $key => $value) {
    if ($value !== '' && $value !== null) {
        $vitalsPrefill[$key] = $value;
    }
}

// ---------------------------------------------------------------------
// "Created by" footer — createdBy on the consultation doc is a uid
// (api/create-consultation.php), resolved here to a real display name
// rather than showing a raw id.
// ---------------------------------------------------------------------
$createdByName = '—';
if ($consultation['createdBy'] !== '') {
    $createdByDoc = $firestore->getDocument('users', $consultation['createdBy']);
    $createdByName = $createdByDoc['name'] ?? '—';
}

$startedAtLabel = $consultation['startedAt'] !== '' ? date('M j, Y g:i A', strtotime($consultation['startedAt'])) : '—';

// ---------------------------------------------------------------------
// Lab + X-ray Results — shown as soon as the medical technologist
// marks a test "completed", NOT only once they separately click
// "Proceed to Doctor" on top of that. Those are two different real
// signals that used to be conflated:
//   - labResultDocIds — only set once a medtech explicitly routes a
//     result back (api/proceed-lab-result-to-doctor.php).
//   - a lab_results/xray_results doc's own real 'status' field —
//     flips to "completed" the moment the medtech saves the result
//     form, independent of that separate routing step ever happening.
// A doctor reopening an ongoing consultation (e.g. the checkbox grid
// below showing "Gram Stain (result received)" via $labTestsAlreadyOrdered)
// could see a test marked received with NO way to actually open it,
// because nothing had routed it back yet — this union fixes that: a
// completed test ordered FROM this consultation (sourceConsultationId
// match) shows here regardless of whether it was also routed.
// Deduplicated by doc id since a routed result is often the same doc
// a sourceConsultationId match would already find.
// ---------------------------------------------------------------------
$labDocsForPatient = hc_get_lab_result_docs_for_patient($consultation['patientId']);
$xrayDocsForPatient = hc_get_xray_result_docs_for_patient($consultation['patientId']);

$labDocsById = array_column($labDocsForPatient, 'fields', 'id');
$xrayDocsById = array_column($xrayDocsForPatient, 'fields', 'id');

$resultIdsToShow = $consultation['labResultDocIds'];
foreach ($labDocsForPatient as $doc) {
    if (($doc['fields']['sourceConsultationId'] ?? '') === $consultation['id'] && ($doc['fields']['status'] ?? '') === 'completed') {
        $resultIdsToShow[] = $doc['id'];
    }
}
foreach ($xrayDocsForPatient as $doc) {
    if (($doc['fields']['sourceConsultationId'] ?? '') === $consultation['id'] && ($doc['fields']['status'] ?? '') === 'completed') {
        $resultIdsToShow[] = $doc['id'];
    }
}
$resultIdsToShow = array_unique($resultIdsToShow);

$linkedLabResults = [];
foreach ($resultIdsToShow as $resultId) {
    $labDoc = $labDocsById[$resultId] ?? $firestore->getDocument('lab_results', $resultId);
    if ($labDoc !== null) {
        $badgeColor = $labDoc['resultBadgeColor'] ?? '#6B7280';
        $linkedLabResults[] = [
            'id' => $resultId,
            'type' => 'lab',
            'testName' => $labDoc['testName'] ?? '—',
            'resultBadgeLabel' => $labDoc['resultBadgeLabel'] ?? '—',
            'resultBadgeColor' => $badgeColor,
            // Same light-background mapping every other role's lab badge
            // uses (includes/medical_technician/laboratory_xray_firestore.php's
            // hc_medtech_badge_bg_for_color()) — duplicated here rather
            // than requiring that whole file just for this one helper.
            'resultBadgeBg' => match ($badgeColor) {
                '#1E8E3E' => '#E4F7EA',
                '#E8710A' => '#FDECDD',
                '#D93025' => '#FCE4E4',
                '#1A73E8' => '#E8F0FE',
                '#7C3AED' => '#EFE7FB',
                default => '#EEF0F3',
            },
            'completedDate' => ($labDoc['completedDate'] ?? '') !== '' ? date('M j, Y', strtotime($labDoc['completedDate'])) : '—',
            'completedTime' => $labDoc['completedTime'] ?? '—',
        ];
        continue;
    }

    $xrayDoc = $xrayDocsById[$resultId] ?? $firestore->getDocument('xray_results', $resultId);
    if ($xrayDoc === null) {
        continue;
    }
    // xray_results has no resultBadgeLabel/resultBadgeColor of its own
    // (that's a lab_results-only field) — same real "no active"
    // heuristic includes/firestore/patient_overview.php's Overview tab
    // already uses to call an x-ray normal vs. abnormal from its
    // impression text.
    $isNormal = str_contains(strtolower($xrayDoc['impression'] ?? ''), 'no active');
    $linkedLabResults[] = [
        'id' => $resultId,
        'type' => 'xray',
        'testName' => $xrayDoc['examName'] ?? '—',
        'resultBadgeLabel' => $isNormal ? 'Normal' : 'Abnormal',
        'resultBadgeColor' => $isNormal ? '#1E8E3E' : '#D93025',
        'resultBadgeBg' => $isNormal ? '#E4F7EA' : '#FCE4E4',
        'completedDate' => ($xrayDoc['completedDate'] ?? '') !== '' ? date('M j, Y', strtotime($xrayDoc['completedDate'])) : '—',
        'completedTime' => $xrayDoc['completedTime'] ?? '—',
    ];
}

// Tests already ordered FROM THIS consultation (real lab_results docs
// whose sourceConsultationId matches — api/complete-consultation.php
// stamps that on every one it creates), keyed by test name -> real
// status ("pending" still awaiting the medical technologist,
// "completed" already resolved — see $linkedLabResults above for
// those). The checkbox grid below locks these so re-saving with
// Laboratory still selected can't silently re-order the SAME test a
// second time — this is exactly how the real duplicate Gram Stain
// orders on a live consultation happened before this existed.
$labTestsAlreadyOrdered = [];
foreach ($labDocsForPatient as $doc) {
    $f = $doc['fields'];
    if (($f['sourceConsultationId'] ?? '') === $consultation['id']) {
        $labTestsAlreadyOrdered[$f['testName'] ?? ''] = $f['status'] ?? 'pending';
    }
}

// "Patient Will Be Next In" — one of three mutually-exclusive "right
// now" destinations (recorded on the consultation only, nothing
// auto-created elsewhere besides Laboratory's own real lab_results/
// xray_results docs and Pharmacy's own real pharmacy_orders docs — see
// api/complete-consultation.php). "Needs a follow-up visit" is a
// SEPARATE, independent checkbox below this list (not a fourth radio
// here) — a patient can need a follow-up scheduled AND still go to the
// pharmacy today for medicine from THIS visit, so it can't be mutually
// exclusive with the other three the way they are with each other.
$routingOptions = [
    'laboratory'  => ['label' => 'Laboratory',  'desc' => 'Send patient to laboratory for tests.',   'icon' => 'bi-droplet-half'],
    'pharmacy'    => ['label' => 'Pharmacy',    'desc' => 'Send patient to pharmacy for medicines.', 'icon' => 'bi-capsule'],
    'vaccination' => ['label' => 'Vaccination', 'desc' => 'Send patient for vaccination.',            'icon' => 'bi-shield-plus'],
];

// Selecting "Laboratory" above reveals a "Select Laboratory Tests"
// checkbox grid, grouped by this same fixed catalog
// (hc_lab_test_catalog(), includes/firestore/lab_results.php) — reused
// server-side by api/complete-consultation.php to validate submitted
// test names and look up each one's real category, rather than
// trusting a client-supplied category string.
$labTestCategories = hc_lab_test_catalog();

// Medications & Vaccines table — real names for autocomplete only (free
// text is still valid, same convention as
// includes/pharmacist/new_stock_request_modal.php's item rows); a
// consultation already Saved (re-opened while still "ongoing") shows
// its own real rows instead of starting blank.
$medicineNames = array_column(hc_get_inventory_items('medicine'), 'name');
$vaccineNames = array_column(hc_get_inventory_items('vaccine'), 'name');
