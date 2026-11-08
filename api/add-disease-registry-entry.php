<?php
/**
 * =====================================================================
 * Add Disease Registry Entry (api/add-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Creates a new disease_registry entry, typed from scratch on
 * admin/disease_registry.php. Admin-only (system-wide scope).
 *
 * Every new entry is created registryStatus='classified',
 * gisEligible=true, publicVisibility=true — it becomes visible on the
 * public GIS map immediately. There is no separate classification/
 * eligibility review step any more; the admin UI shows a reminder
 * (includes/admin/manage_disease_registry_modal.php's #mdrAddReminder)
 * before the admin submits this form, so that's never a surprise.
 * `classification` (used only for the GIS dynamic classification
 * filter) starts null — this form has no control for it.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/clinical_classification.php'; // hc_normalize_text()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

function adre_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'admin') {
    adre_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adre_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string) ($body['name'] ?? ''));
$synonymsRaw = (string) ($body['synonyms'] ?? '');
$isPreventable = !empty($body['isPreventable']);
$behavioralMeasures = mb_substr(trim((string) ($body['behavioralMeasures'] ?? '')), 0, 500);
$environmentalMeasures = mb_substr(trim((string) ($body['environmentalMeasures'] ?? '')), 0, 500);
$communityMeasures = mb_substr(trim((string) ($body['communityMeasures'] ?? '')), 0, 500);

if ($name === '') {
    adre_fail(422, 'Please enter a condition name.');
}

$synonyms = array_values(array_unique(array_filter(
    array_map('hc_normalize_text', explode(',', $synonymsRaw)),
    fn ($s) => $s !== ''
)));
if (empty($synonyms)) {
    adre_fail(422, 'Please enter at least one synonym.');
}

// Reject a duplicate name (case-insensitive) rather than silently
// creating a second entry for the same real condition.
foreach ($firestore->listDocuments('disease_registry') as $doc) {
    if (strcasecmp((string) ($doc['fields']['name'] ?? ''), $name) === 0) {
        adre_fail(422, 'A registry entry named "' . $name . '" already exists.');
    }
}

$adminName = $_SESSION['name'] ?? 'Administrator';
$adminUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');
$today = date('Y-m-d');

$newId = $firestore->addDocument('disease_registry', [
    'name' => $name,
    'synonyms' => $synonyms,
    'classification' => null, // no control for this on the add form — stays unassigned
    'registryStatus' => 'classified',
    'gisEligible' => true,
    'publicVisibility' => true,
    'isPreventable' => $isPreventable,
    'behavioralMeasures' => $behavioralMeasures,
    'environmentalMeasures' => $environmentalMeasures,
    'communityMeasures' => $communityMeasures,
    'isAutoDetected' => false,
    'createdAt' => $nowIso,
    'updatedByName' => $adminName, 'updatedByUid' => $adminUid, 'updatedByRole' => 'admin',
    'updatedAt' => $nowIso,
    'history' => [[
        'date' => $today,
        'event' => 'Created by admin — visible on GIS Mapping immediately',
        'previousGisEligible' => null, 'newGisEligible' => true,
        'previousPublicVisibility' => null, 'newPublicVisibility' => true,
        'changedByName' => $adminName, 'changedByUid' => $adminUid, 'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ]],
]);

if ($newId === null) {
    adre_fail(500, 'Could not create this entry. Please try again.');
}

write_audit_log('created', 'Disease Registry', 'Added "' . $name . '" to the disease registry — now visible on GIS Mapping.');

echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
