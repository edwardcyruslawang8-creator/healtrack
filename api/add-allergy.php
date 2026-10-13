<?php
/**
 * =====================================================================
 * Add Allergy (api/add-allergy.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/active_consultation.js when a doctor adds a new
 * allergy from the "Allergies" card on doctor/active_consultation.php
 * — the first WRITE path the real "allergies" collection has ever had
 * outside of patient registration (api/create-patient.php:251-271,
 * whose exact field shape this matches so
 * includes/firestore/allergies.php's hc_get_allergies_for_patient()
 * reads it back correctly — every field it accesses is required,
 * without a "??" fallback).
 *
 * Allergies are patient-wide, not scoped to one consultation (there's
 * no consultationId field on this collection, same as
 * api/create-patient.php's writes) — a real, permanent record on the
 * patient the moment it's added, independent of whether the doctor
 * goes on to Save & End or Cancel this consultation.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
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

$patientId = trim((string) ($body['patientId'] ?? ''));
$allergen = trim((string) ($body['allergen'] ?? ''));
$reaction = trim((string) ($body['reaction'] ?? '')) ?: 'Not specified';
$severity = trim((string) ($body['severity'] ?? ''));
$severity = in_array($severity, ['mild', 'moderate', 'severe'], true) ? $severity : 'mild';

if ($patientId === '' || $allergen === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter an allergen.']);
    exit;
}

$today = date('Y-m-d');

$newId = $firestore->addDocument('allergies', [
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
    // $_SESSION['name'] for a doctor account already includes "Dr." (see
    // real users/{uid}.name values, e.g. "Dr. Juan Dela Cruz") — not
    // prepended again here to avoid "Dr. Dr. ...".
    'notes' => 'Recorded during consultation by ' . ($_SESSION['name'] ?? 'a doctor'),
    'recommendations' => '—',
    'summary' => $allergen . ' allergy — ' . $reaction . '.',
    'description' => 'Recorded during consultation.',
    'symptoms' => [],
    'history' => [],
]);

if ($newId === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save this allergy. Please try again.']);
    exit;
}

write_audit_log('created', 'Patient Management', 'Added a new allergy (' . $allergen . ') for patient ' . $patientId . '.');
hc_record_patient_history($patientId, 'allergy', 'added', 'Added allergy "' . $allergen . '" (severity: ' . ucfirst($severity) . ').');
hc_record_patient_activity($patientId, 'Allergy "' . $allergen . '" recorded.');

echo json_encode(['success' => true, 'id' => $newId]);
