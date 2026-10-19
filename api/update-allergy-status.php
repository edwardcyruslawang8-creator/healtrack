<?php
/**
 * =====================================================================
 * Update Allergy Status (api/update-allergy-status.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/allergy-actions.js from medical-staff/allergies.php's
 * "Mark Inactive" (Active Allergies table) and "Reactivate"
 * (Inactive / Resolved Allergies table) actions, each only after the
 * staff member confirms.
 *
 * Flips ONE existing allergies/{id} doc between "active" and
 * "resolved" (the existing status convention — everything not
 * "active" is listed as Inactive / Resolved). It is never deleted and
 * no other field is touched — allergen, type, reaction, severity,
 * onset date, and notes are kept as they are. The change is appended to
 * the record's own "history" (shown on allergy_details.php) and stamped
 * as statusUpdatedOn/By, which feeds the page's Last Updated card.
 * Only ever done by an explicit staff action — nothing auto-resolves.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/document_storage.php'; // hc_user_can_access_patient_document()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()
require_once __DIR__ . '/../includes/firestore/patient_history.php'; // hc_record_patient_history(), hc_record_patient_activity()

header('Content-Type: application/json');

function uas_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    uas_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uas_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$allergyId = trim((string) ($body['allergyId'] ?? ''));
$action = trim((string) ($body['action'] ?? ''));

if ($allergyId === '' || !in_array($action, ['deactivate', 'reactivate'], true)) {
    uas_fail(422, 'Invalid request.');
}

$allergy = $firestore->getDocument('allergies', $allergyId);
if ($allergy === null) {
    uas_fail(404, 'This allergy record could not be found.');
}

$patientId = (string) ($allergy['patientId'] ?? '');
$patient = $patientId !== '' ? $firestore->getDocument('patients', $patientId) : null;
if ($patient === null) {
    uas_fail(404, 'This allergy record could not be found.');
}
$patient['id'] = $patientId;
if (!hc_user_can_access_patient_document($patient)) {
    uas_fail(404, 'This allergy record could not be found.');
}

$allergen = (string) ($allergy['allergen'] ?? 'Allergy');
$isActive = ($allergy['status'] ?? '') === 'active';

// Stale page (someone else already changed it) — don't flip it back.
if ($action === 'deactivate' && !$isActive) {
    uas_fail(409, '"' . $allergen . '" is already inactive / resolved. Refresh the page to see its current status.');
}
if ($action === 'reactivate' && $isActive) {
    uas_fail(409, '"' . $allergen . '" is already active. Refresh the page to see its current status.');
}

$staffName = $_SESSION['name'] ?? 'Medical Staff';
$today = date('Y-m-d');

// setDocument() overwrites the whole doc, so write back every existing
// field with only the status-related ones changed.
$updated = $allergy;
$updated['status'] = $action === 'deactivate' ? 'resolved' : 'active';
$updated['statusUpdatedOn'] = $today;
$updated['statusUpdatedAt'] = date('c');
$updated['statusUpdatedBy'] = $staffName;
$updated['history'] = array_values(is_array($allergy['history'] ?? null) ? $allergy['history'] : []);
$updated['history'][] = $action === 'deactivate'
    ? ['date' => $today, 'event' => 'Marked inactive / resolved', 'details' => 'Marked inactive / resolved by ' . $staffName . '.']
    : ['date' => $today, 'event' => 'Reactivated', 'details' => 'Reactivated by ' . $staffName . '.'];

if (!$firestore->setDocument('allergies', $allergyId, $updated)) {
    uas_fail(500, 'Could not update this allergy. Please try again.');
}

$what = $action === 'deactivate' ? 'Marked allergy "' . $allergen . '" as inactive / resolved' : 'Reactivated allergy "' . $allergen . '"';
write_audit_log('updated', 'Patient Management', $what . ' for ' . ($patient['name'] ?? 'a patient') . '.');
hc_record_patient_history($patientId, 'allergy', 'updated', $what . '.');
hc_record_patient_activity($patientId, $what . '.');

echo json_encode([
    'success' => true,
    'status' => $updated['status'],
    'message' => $action === 'deactivate'
        ? '"' . $allergen . '" was marked as inactive / resolved.'
        : '"' . $allergen . '" was reactivated.',
]);
