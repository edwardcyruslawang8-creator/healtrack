<?php
/**
 * =====================================================================
 * Create Patient (api/create-patient.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/add-patient.js when a medical staff member or
 * doctor completes the 5-step "Add Patient" wizard
 * (includes/medical-staff/add_patient_modal.php — reused directly,
 * unchanged, by doctor/patient_management.php too, since nothing in it
 * is medical-staff-specific). Writes:
 *   - one new document to "patients" (personal info + vitals snapshot)
 *   - zero or more documents to "medical_conditions", "surgical_history",
 *     "allergies", and "medications", each tagged with the new patientId
 * — the same collections includes/firestore/patients.php, medical_records.php,
 * allergies.php, and medications.php read for every role's Patient
 * Profile page ("surgical_history" has no reader yet — see that block
 * below). Condition/allergy/medication rows are filled in with the
 * same default field shape those read functions expect (see each
 * block below) so the Patient Profile tabs don't break when this
 * patient is viewed later.
 *
 * Does NOT create a Firebase Auth login account (a separate, unrelated
 * concern from having a patient's chart exist) and does NOT create a
 * "consultations" record — there's no such collection yet (see
 * medical-staff/consultations.php's header comment), so Reason for
 * Visit is stored as-entered on the patient doc, not fabricated into a
 * consultation.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

// Only a signed-in medical staff member or doctor may register a new patient.
if (!in_array($_SESSION['role'] ?? null, ['healthcare_staff', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

// The registering staff member's own real facility — same resolution
// api/complete-consultation.php's $doctorFacility already uses
// ($_SESSION never carries "facility" directly anywhere in this app;
// every write path re-reads it from the real users/{uid} doc). Without
// this, a patient doc never carries a real facility at all, which
// silently breaks every "this facility's own patients" join elsewhere
// (includes/admin/facility_analytics_firestore.php's Overview AND
// Predictive tabs both filter by this field).
$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;
$registeringFacility = $myDoc['facility'] ?? 'North Super Health Center';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$firstName = trim((string) ($body['firstName'] ?? ''));
$middleName = trim((string) ($body['middleName'] ?? ''));
$lastName = trim((string) ($body['lastName'] ?? ''));
$birthDate = trim((string) ($body['birthDate'] ?? ''));
$sex = trim((string) ($body['sex'] ?? ''));
$civilStatus = trim((string) ($body['civilStatus'] ?? ''));
$occupation = trim((string) ($body['occupation'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$address = trim((string) ($body['address'] ?? ''));
$barangay = trim((string) ($body['barangay'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));
$consent = trim((string) ($body['consent'] ?? ''));

// Blood Type — optional (Step 2 of the wizard); only validated against
// the standard 8 types when actually provided, never required.
$bloodType = trim((string) ($body['bloodType'] ?? ''));
if ($bloodType !== '' && !in_array($bloodType, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please select a valid blood type.']);
    exit;
}

if ($firstName === '' || $lastName === '' || $birthDate === '' || $sex === '' || $civilStatus === ''
    || $phone === '' || $address === '' || $barangay === '' || $city === '' || $consent === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field in Personal Information.']);
    exit;
}

$birthTimestamp = strtotime($birthDate);
if ($birthTimestamp === false || $birthTimestamp > time()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid birth date.']);
    exit;
}

$reasonForVisit = trim((string) ($body['reasonForVisit'] ?? ''));
if ($reasonForVisit === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a reason for visit.']);
    exit;
}

$age = (int) date_diff(date_create('@' . $birthTimestamp), date_create('@' . time()))->y;
$fullName = trim(preg_replace('/\s+/', ' ', $firstName . ' ' . $middleName . ' ' . $lastName));

// Vital Signs — all six core fields are required by the wizard's Step
// 2, with the same value ranges enforced client-side (see
// assets/js/add-patient.js / includes/medical-staff/add_patient_modal.php).
// Re-validated here since client-side checks are trivially bypassable.
$vitals = is_array($body['vitals'] ?? null) ? $body['vitals'] : [];
$vitalDateTime = trim((string) ($vitals['dateTimeTaken'] ?? ''));
$bpSystolic = trim((string) ($vitals['bloodPressureSystolic'] ?? ''));
$bpDiastolic = trim((string) ($vitals['bloodPressureDiastolic'] ?? ''));
$heartRate = trim((string) ($vitals['heartRate'] ?? ''));
$temperature = trim((string) ($vitals['temperature'] ?? ''));
$weight = trim((string) ($vitals['weight'] ?? ''));
$height = trim((string) ($vitals['height'] ?? ''));

if ($vitalDateTime === '' || $bpSystolic === '' || $bpDiastolic === '' || $heartRate === ''
    || $temperature === '' || $weight === '' || $height === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill out every required field in Vital Signs.']);
    exit;
}
if (!is_numeric($bpSystolic) || !is_numeric($bpDiastolic)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Blood pressure must be numeric.']);
    exit;
}
if (!is_numeric($heartRate) || $heartRate < 30 || $heartRate > 220) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Heart rate must be between 30 and 220 bpm.']);
    exit;
}
if (!is_numeric($temperature) || $temperature < 30 || $temperature > 45) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Temperature must be between 30°C and 45°C.']);
    exit;
}
if (!is_numeric($weight) || $weight <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid weight.']);
    exit;
}
if (!is_numeric($height) || $height <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid height.']);
    exit;
}

// BMI computed server-side (authoritative), not trusted from the client.
$heightMeters = ((float) $height) / 100;
$bmi = round(((float) $weight) / ($heightMeters * $heightMeters), 1);

$intakeVitals = [
    'dateTimeTaken' => $vitalDateTime,
    'bloodPressureSystolic' => (float) $bpSystolic,
    'bloodPressureDiastolic' => (float) $bpDiastolic,
    'heartRate' => (float) $heartRate,
    'temperature' => (float) $temperature,
    'weight' => (float) $weight,
    'height' => (float) $height,
    'bmi' => $bmi,
];

$patientFields = [
    'name' => $fullName,
    'firstName' => $firstName,
    'middleName' => $middleName !== '' ? $middleName : null,
    'lastName' => $lastName,
    'birthDate' => date('Y-m-d', $birthTimestamp),
    'age' => $age,
    'gender' => $sex,
    'civilStatus' => $civilStatus,
    'occupation' => $occupation !== '' ? $occupation : null,
    'contact' => $phone,
    'address' => $address,
    'barangay' => $barangay,
    'city' => $city,
    'facility' => $registeringFacility,
    'bloodType' => $bloodType !== '' ? $bloodType : null,
    'consentShareInfo' => $consent === 'yes',
    'primaryPhysician' => $_SESSION['name'] ?? null,
    'dateRegistered' => date('c'),
    'registeredBy' => $_SESSION['uid'] ?? '',
    'intakeVitals' => $intakeVitals,
    'visitType' => trim((string) ($body['visitType'] ?? '')) ?: 'New Patient Registration',
    'initialReasonForVisit' => $reasonForVisit,
    'associatedSymptoms' => trim((string) ($body['associatedSymptoms'] ?? '')) ?: null,
    'medicationNotes' => trim((string) ($body['medicationNotes'] ?? '')) ?: null,
];

$patientId = $firestore->addDocument('patients', $patientFields);

if ($patientId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the patient record. Please try again.']);
    exit;
}

// ---------------------------------------------------------------------
// Medical History -> real "medical_conditions" docs, in the exact
// shape includes/firestore/medical_records.php's
// hc_get_medical_conditions_for_patient() reads back ("status" and
// "diagnosedOn" aren't collected by this step of the wizard anymore,
// so they get sensible defaults; "details" is stored alongside for
// future use even though that read function doesn't surface it yet).
// A row is only kept if its Medical Condition is filled in — the
// wizard already enforces "condition required if details are filled",
// so any row that reaches here with a blank label had a blank
// Details/Others too.
// ---------------------------------------------------------------------
$conditions = is_array($body['conditions'] ?? null) ? $body['conditions'] : [];
foreach ($conditions as $c) {
    $label = trim((string) ($c['label'] ?? ''));
    if ($label === '') {
        continue;
    }

    $firestore->addDocument('medical_conditions', [
        'patientId' => $patientId,
        'label' => $label,
        'details' => trim((string) ($c['details'] ?? '')) ?: null,
        'status' => 'Active',
        'diagnosedOn' => date('Y-m-d'),
    ]);
}

// ---------------------------------------------------------------------
// Surgical History -> real "surgical_history" docs, tagged with the
// new patientId. No page reads this collection back yet (there's no
// Surgical History tab on the Patient Profile), but it gets its own
// collection for the same reason medical_conditions/allergies/
// medications each do: one real place per data type, ready to surface
// later without a schema change.
// ---------------------------------------------------------------------
$surgeries = is_array($body['surgeries'] ?? null) ? $body['surgeries'] : [];
foreach ($surgeries as $s) {
    $procedure = trim((string) ($s['procedure'] ?? ''));
    if ($procedure === '') {
        continue;
    }

    $firestore->addDocument('surgical_history', [
        'patientId' => $patientId,
        'procedure' => $procedure,
        'details' => trim((string) ($s['details'] ?? '')) ?: null,
        'dateRecorded' => date('Y-m-d'),
    ]);
}

// ---------------------------------------------------------------------
// Known Allergies -> real "allergies" docs, in the exact shape
// includes/firestore/allergies.php's hc_get_allergies_for_patient() reads
// back (every field it accesses directly, without a "??" fallback,
// must be present).
// ---------------------------------------------------------------------
$allergies = is_array($body['allergies'] ?? null) ? $body['allergies'] : [];
foreach ($allergies as $a) {
    $allergen = trim((string) ($a['allergen'] ?? ''));
    if ($allergen === '') {
        continue;
    }
    $reaction = trim((string) ($a['reaction'] ?? '')) ?: 'Not specified';
    $severity = trim((string) ($a['severity'] ?? ''));
    $severity = in_array($severity, ['mild', 'moderate', 'severe'], true) ? $severity : 'mild';
    $today = date('Y-m-d');

    $firestore->addDocument('allergies', [
        'patientId' => $patientId,
        'allergen' => $allergen,
        'subtype' => null,
        'type' => 'Other',
        'icon' => 'bi-exclamation-triangle-fill',
        'iconBg' => '#FDECDD',
        'iconColor' => '#E8710A',
        'reaction' => $reaction,
        'severity' => $severity,
        'onsetDate' => $today,
        'lastOccurred' => $today,
        'addedOn' => $today,
        'status' => 'active',
        'notes' => 'Recorded during patient registration.',
        'recommendations' => '—',
        'summary' => $allergen . ' allergy — ' . $reaction . '.',
        'description' => 'Recorded during patient registration.',
        'symptoms' => [],
        'history' => [],
    ]);
}

// ---------------------------------------------------------------------
// Current Medications -> real "medications" docs, in the exact shape
// includes/firestore/medications.php's hc_get_medications_for_patient()
// reads back.
// ---------------------------------------------------------------------
$medications = is_array($body['medications'] ?? null) ? $body['medications'] : [];
foreach ($medications as $m) {
    $name = trim((string) ($m['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $startDate = trim((string) ($m['startDate'] ?? ''));

    $firestore->addDocument('medications', [
        'patientId' => $patientId,
        'name' => $name,
        'category' => 'General',
        'dosage' => trim((string) ($m['dosage'] ?? '')) ?: 'Not specified',
        'frequency' => trim((string) ($m['frequency'] ?? '')) ?: 'Not specified',
        'route' => 'Oral',
        'indication' => 'Not specified',
        'startDate' => (strtotime($startDate) !== false && $startDate !== '') ? $startDate : date('Y-m-d'),
        'endDate' => null,
        'endDateNote' => null,
        'prescribedBy' => trim((string) ($m['prescribedBy'] ?? '')) ?: ($_SESSION['name'] ?? 'Not specified'),
        'notes' => 'Recorded during patient registration.',
        'status' => 'active',
    ]);
}

write_audit_log('created', 'Patient Management', 'Registered new patient "' . $fullName . '".');
hc_record_patient_history($patientId, 'patient_profile', 'added', 'Patient record created.');
hc_record_patient_activity($patientId, 'Patient registered in the system.');

echo json_encode([
    'success' => true,
    'patient' => [
        'id' => $patientId,
        'name' => $fullName,
        'age' => $age,
        'gender' => $sex,
        'contact' => $phone,
    ],
]);
