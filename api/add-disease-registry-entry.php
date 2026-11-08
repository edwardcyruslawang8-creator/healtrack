<?php
/**
 * =====================================================================
 * Add Disease Registry Entry (api/add-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Creates a new disease_registry entry, typed from scratch on
 * admin/disease_registry.php. Admin-only (system-wide scope).
 *
 * Server FORCES a brand-new entry to start registryStatus='unclassified',
 * gisEligible=false, publicVisibility=false, ignoring any value the
 * client sends for those — a newly-recognized condition is never
 * accidentally public before someone's actually reviewed it. A
 * classification is assigned afterward, as ordinary maintenance, via
 * api/update-disease-registry-entry.php.
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
    'classification' => null, // always — never accepts a client-supplied classification, see this file's own header comment
    'registryStatus' => 'unclassified',
    'gisEligible' => false,
    'publicVisibility' => false,
    'isPreventable' => $isPreventable,
    'behavioralMeasures' => '',
    'environmentalMeasures' => '',
    'communityMeasures' => '',
    'isAutoDetected' => false,
    'createdAt' => $nowIso,
    'updatedByName' => $adminName, 'updatedByUid' => $adminUid, 'updatedByRole' => 'admin',
    'updatedAt' => $nowIso,
    'history' => [[
        'date' => $today,
        'event' => 'Created — unclassified (excluded from GIS)',
        'previousClassification' => null, 'newClassification' => null,
        'previousGisEligible' => null, 'newGisEligible' => false,
        'previousPublicVisibility' => null, 'newPublicVisibility' => false,
        'changedByName' => $adminName, 'changedByUid' => $adminUid, 'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ]],
]);

if ($newId === null) {
    adre_fail(500, 'Could not create this entry. Please try again.');
}

write_audit_log('created', 'Disease Registry', 'Added "' . $name . '" to the disease registry (unclassified).');

echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
