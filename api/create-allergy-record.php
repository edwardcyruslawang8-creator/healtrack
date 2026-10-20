<?php
/**
 * =====================================================================
 * Create Allergy Record (api/create-allergy-record.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/allergy-actions.js from the "Add Allergy Record"
 * modal (includes/medical-staff/add_allergy_modal.php) on
 * medical-staff/allergies.php. Writes to the real "allergies"
 * collection in the exact field shape includes/firestore/allergies.php's
 * hc_get_allergies_for_patient() reads back (every field it accesses
 * without a "??" fallback is present) — same shape as
 * api/create-patient.php and api/add-allergy.php, which stay as they
 * are for registration and doctor consultations.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()
require_once __DIR__ . '/../includes/firestore/allergies.php'; // hc_allergy_type_meta()

header('Content-Type: application/json');

function car_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    car_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    car_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$text = fn (string $key, int $maxLen = 160) => mb_substr(trim((string) ($body[$key] ?? '')), 0, $maxLen);

$patientId = $text('patientId', 128);
$allergen = $text('allergen', 120);
$type = $text('type');
$reaction = $text('reaction', 200);
$severity = $text('severity');
$onsetRaw = $text('onsetDate');
$status = $text('status');
$notes = $text('notes', 500);
$today = date('Y-m-d');

$typeMeta = hc_allergy_type_meta();

if ($patientId === '' || $allergen === '' || $reaction === '' || $onsetRaw === '') {
    car_fail(422, 'Please fill in the allergen, reaction, and onset date.');
}
if (!array_key_exists($type, $typeMeta)) {
    car_fail(422, 'Please select the allergy type.');
}
if (!in_array($severity, ['mild', 'moderate', 'severe'], true)) {
    car_fail(422, 'Please select the severity.');
}
if (!in_array($status, ['active', 'resolved'], true)) {
    car_fail(422, 'Please select the status.');
}
$onset = DateTime::createFromFormat('!Y-m-d', $onsetRaw);
if (!$onset || $onset->format('Y-m-d') !== $onsetRaw) {
    car_fail(422, 'Please enter a valid onset date.');
}
if ($onsetRaw > $today) {
    car_fail(422, 'Onset date cannot be in the future.');
}

$patient = $firestore->getDocument('patients', $patientId);
if ($patient === null) {
    car_fail(404, 'This patient could not be found.');
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    car_fail(404, 'This patient could not be found.');
}

$birthDate = !empty($patient['birthDate']) && strtotime($patient['birthDate']) ? date('Y-m-d', strtotime($patient['birthDate'])) : null;
if ($birthDate !== null && $onsetRaw < $birthDate) {
    car_fail(422, 'Onset date cannot be before the patient\'s birth date.');
}

// The same allergen already on file — avoid a duplicate record. A
// resolved one should be reactivated instead, keeping its history.
foreach ($firestore->listDocuments('allergies') as $doc) {
    $f = $doc['fields'];
    if (($f['patientId'] ?? '') !== $patientId || strcasecmp(trim($f['allergen'] ?? ''), $allergen) !== 0) {
        continue;
    }
    if (($f['status'] ?? '') === 'active') {
        car_fail(409, '"' . $allergen . '" is already recorded as an active allergy for this patient.');
    }
    car_fail(409, '"' . $allergen . '" is already on file as an inactive / resolved allergy. Use Reactivate in the Inactive / Resolved Allergies table instead.');
}

$staffName = $_SESSION['name'] ?? 'Medical Staff';
$sevLabel = ucfirst($severity);

$newId = $firestore->addDocument('allergies', [
    'patientId' => $patientId,
    'allergen' => $allergen,
    'subtype' => null,
    'type' => $type,
    'icon' => $typeMeta[$type]['icon'],
    'iconBg' => $typeMeta[$type]['iconBg'],
    'iconColor' => $typeMeta[$type]['iconColor'],
    'reaction' => $reaction,
    'severity' => $severity,
    'onsetDate' => $onsetRaw,
    'lastOccurred' => $onsetRaw,
    'addedOn' => $today,
    'status' => $status,
    'notes' => $notes !== '' ? $notes : '—',
    'recommendations' => '—',
    'summary' => $allergen . ' allergy — ' . $reaction . '.',
    'description' => 'Recorded by ' . $staffName . '.',
    'symptoms' => [],
    'history' => [
        ['date' => $today, 'event' => 'Allergy recorded', 'details' => 'Recorded by ' . $staffName . ' (severity: ' . $sevLabel . ', status: ' . ($status === 'active' ? 'Active' : 'Resolved') . ').'],
    ],
    'createdBy' => $staffName,
    'createdByUid' => $_SESSION['uid'] ?? '',
]);

if ($newId === null) {
    car_fail(500, 'Could not save this allergy. Please try again.');
}

write_audit_log('created', 'Patient Management', 'Added a new allergy (' . $allergen . ') for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'allergy', 'added', 'Added allergy "' . $allergen . '" (severity: ' . $sevLabel . ').');
hc_record_patient_activity($patientId, 'Allergy "' . $allergen . '" recorded.');

echo json_encode(['success' => true, 'id' => $newId]);
