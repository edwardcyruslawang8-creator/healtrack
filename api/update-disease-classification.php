<?php
/**
 * =====================================================================
 * Update Disease Classification (api/update-disease-classification.php)
 * ---------------------------------------------------------------------
 * The ONE real decision point for every disease public-visibility
 * classification change — same shape api/update-allergy-clinical-status.php
 * already established for clinical status changes: a required
 * documented reason, the authenticated admin's own identity (from
 * $_SESSION, never client-supplied), and an append-only "history" array
 * recording every transition, so nothing ever silently changes a
 * condition's public visibility.
 *
 * Admin-only (system-wide scope — see
 * includes/firestore/disease_registry.php's header comment).
 *
 * Deliberately separate from api/update-disease-registry-entry.php
 * (name/keywords/preventable — routine maintenance, no reason needed)
 * so this endpoint's audit trail is only ever about real visibility
 * decisions.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

function udc_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'admin') {
    udc_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    udc_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));
$newClassification = trim((string) ($body['classification'] ?? ''));
$newPreventiveMeasures = trim((string) ($body['preventiveMeasures'] ?? ''));
$reason = trim((string) ($body['reason'] ?? ''));

$allowedClassifications = ['public_awareness', 'conditional_public_display', 'internal_surveillance_only', 'pending_classification'];

if ($id === '') {
    udc_fail(422, 'Missing registry entry.');
}
if (!in_array($newClassification, $allowedClassifications, true)) {
    udc_fail(422, 'Please select a valid classification.');
}
if (mb_strlen($reason) < 10) {
    udc_fail(422, 'Please document the basis for this classification (at least 10 characters).');
}
$reason = mb_substr($reason, 0, 1000);
$newPreventiveMeasures = mb_substr($newPreventiveMeasures, 0, 1000);

$existing = $firestore->getDocument('disease_registry', $id);
if ($existing === null) {
    udc_fail(404, 'This registry entry could not be found.');
}

$prevClassification = (string) ($existing['classification'] ?? 'pending_classification');
$prevPreventiveMeasures = (string) ($existing['preventiveMeasures'] ?? '');

// No-op guard — a real save must actually change something.
if ($prevClassification === $newClassification && $prevPreventiveMeasures === $newPreventiveMeasures) {
    udc_fail(422, 'No change was made — the selected classification and preventive measures match the current record.');
}

$classificationLabel = [
    'public_awareness' => 'Public Awareness',
    'conditional_public_display' => 'Conditional Public Display',
    'internal_surveillance_only' => 'Internal Surveillance Only',
    'pending_classification' => 'Pending Classification',
];

$adminName = $_SESSION['name'] ?? 'Administrator';
$adminUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');
$today = date('Y-m-d');
$name = (string) ($existing['name'] ?? 'Condition');

$event = 'Classification changed: ' . ($classificationLabel[$prevClassification] ?? $prevClassification) . ' → ' . $classificationLabel[$newClassification];

// setDocument() is a full overwrite — copy every existing field forward
// unchanged, only touch the classification-related fields + history.
$updated = $existing;
$updated['classification'] = $newClassification;
$updated['preventiveMeasures'] = $newPreventiveMeasures;
$updated['classifiedByName'] = $adminName;
$updated['classifiedByUid'] = $adminUid;
$updated['classifiedByRole'] = 'admin';
$updated['classifiedAt'] = $nowIso;
$updated['classificationReason'] = $reason;

$updated['history'] = array_values(is_array($existing['history'] ?? null) ? $existing['history'] : []);
$updated['history'][] = [
    'date' => $today,
    'event' => $event,
    'previousClassification' => $prevClassification,
    'newClassification' => $newClassification,
    'reason' => $reason,
    'previousPreventiveMeasures' => $prevPreventiveMeasures,
    'newPreventiveMeasures' => $newPreventiveMeasures,
    'classifiedByName' => $adminName,
    'classifiedByUid' => $adminUid,
    'classifiedByRole' => 'admin',
    'recordedAt' => $nowIso,
];

if (!$firestore->setDocument('disease_registry', $id, $updated)) {
    udc_fail(500, 'Could not save. Please try again.');
}

write_audit_log(
    'updated',
    'Disease Registry',
    'Changed classification of "' . $name . '" from ' . ($classificationLabel[$prevClassification] ?? $prevClassification) . ' to ' . $classificationLabel[$newClassification] . '. Reason: ' . $reason
);

echo json_encode([
    'success' => true,
    'classification' => $newClassification,
    'message' => '"' . $name . '" classification updated to ' . $classificationLabel[$newClassification] . '.',
]);
