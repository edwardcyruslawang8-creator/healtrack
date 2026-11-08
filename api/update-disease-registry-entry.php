<?php
/**
 * =====================================================================
 * Update Disease Registry Entry (api/update-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Disease-level metadata upkeep — name, synonyms, classification, GIS
 * eligibility, public visibility, preventable flag, and the 3
 * preventive-measure fields. Admin-only (system-wide scope — see
 * includes/shared/clinical_classification.php's header comment for why
 * this isn't subadmin-manageable).
 *
 * Deliberately NOT a per-case approval endpoint — it only ever touches
 * a condition's registry metadata, never an individual patient's
 * consultation/diagnosis record. No "reason" is required (this is
 * ordinary catalog upkeep, same as fixing a typo), but every real
 * change to classification/gisEligible/publicVisibility is appended to
 * the doc's own history[] (the audit log requirement).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/clinical_classification.php'; // hc_disease_registry_classification_meta()
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

function udre_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if (($_SESSION['role'] ?? null) !== 'admin') {
    udre_fail(403, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    udre_fail(405, 'Method not allowed.');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = trim((string) ($body['id'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
$synonymsRaw = (string) ($body['synonyms'] ?? '');
$isPreventable = !empty($body['isPreventable']);
$classification = trim((string) ($body['classification'] ?? ''));
$gisEligible = !empty($body['gisEligible']);
$publicVisibility = !empty($body['publicVisibility']);
$behavioralMeasures = mb_substr(trim((string) ($body['behavioralMeasures'] ?? '')), 0, 500);
$environmentalMeasures = mb_substr(trim((string) ($body['environmentalMeasures'] ?? '')), 0, 500);
$communityMeasures = mb_substr(trim((string) ($body['communityMeasures'] ?? '')), 0, 500);

if ($id === '') {
    udre_fail(422, 'Missing registry entry.');
}
if ($name === '') {
    udre_fail(422, 'Please enter a condition name.');
}
if ($classification !== '' && !array_key_exists($classification, hc_disease_registry_classification_meta())) {
    udre_fail(422, 'Please select a valid classification.');
}

// Comma-separated synonyms, normalized (lowercase, trimmed, deduped,
// blanks dropped) — same matching convention every synonym in this
// registry already uses.
$synonyms = array_values(array_unique(array_filter(
    array_map('hc_normalize_text', explode(',', $synonymsRaw)),
    fn ($s) => $s !== ''
)));
if (empty($synonyms)) {
    udre_fail(422, 'Please enter at least one synonym.');
}

$existing = $firestore->getDocument('disease_registry', $id);
if ($existing === null) {
    udre_fail(404, 'This registry entry could not be found.');
}

$newClassification = $classification !== '' ? $classification : null;
$newRegistryStatus = $newClassification !== null ? 'classified' : 'unclassified';
// Public Visibility can never be true unless GIS Eligible is also true
// and the entry is classified — enforced server-side regardless of
// what the client sends.
$newGisEligible = $newRegistryStatus === 'classified' && $gisEligible;
$newPublicVisibility = $newGisEligible && $publicVisibility;

$prevClassification = $existing['classification'] ?? null;
$prevGisEligible = !empty($existing['gisEligible']);
$prevPublicVisibility = !empty($existing['publicVisibility']);

$adminName = $_SESSION['name'] ?? 'Administrator';
$adminUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');

// setDocument() is a full overwrite — copy every existing field
// forward, only replace the fields this form actually edits.
$updated = $existing;
$updated['name'] = $name;
$updated['synonyms'] = $synonyms;
$updated['isPreventable'] = $isPreventable;
$updated['classification'] = $newClassification;
$updated['registryStatus'] = $newRegistryStatus;
$updated['gisEligible'] = $newGisEligible;
$updated['publicVisibility'] = $newPublicVisibility;
$updated['behavioralMeasures'] = $behavioralMeasures;
$updated['environmentalMeasures'] = $environmentalMeasures;
$updated['communityMeasures'] = $communityMeasures;
$updated['updatedByName'] = $adminName;
$updated['updatedByUid'] = $adminUid;
$updated['updatedByRole'] = 'admin';
$updated['updatedAt'] = $nowIso;

$metadataChanged = $prevClassification !== $newClassification
    || $prevGisEligible !== $newGisEligible
    || $prevPublicVisibility !== $newPublicVisibility;

if ($metadataChanged) {
    $classMeta = hc_disease_registry_classification_meta();
    $updated['history'] = array_values(is_array($existing['history'] ?? null) ? $existing['history'] : []);
    $updated['history'][] = [
        'date' => date('Y-m-d'),
        'event' => 'Metadata changed: classification ' . ($classMeta[$prevClassification] ?? 'None') . ' → ' . ($classMeta[$newClassification] ?? 'None')
            . ', gisEligible ' . ($prevGisEligible ? 'true' : 'false') . ' → ' . ($newGisEligible ? 'true' : 'false')
            . ', publicVisibility ' . ($prevPublicVisibility ? 'true' : 'false') . ' → ' . ($newPublicVisibility ? 'true' : 'false'),
        'previousClassification' => $prevClassification, 'newClassification' => $newClassification,
        'previousGisEligible' => $prevGisEligible, 'newGisEligible' => $newGisEligible,
        'previousPublicVisibility' => $prevPublicVisibility, 'newPublicVisibility' => $newPublicVisibility,
        'changedByName' => $adminName, 'changedByUid' => $adminUid, 'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ];
}

if (!$firestore->setDocument('disease_registry', $id, $updated)) {
    udre_fail(500, 'Could not save. Please try again.');
}

write_audit_log('updated', 'Disease Registry', 'Updated "' . $name . '" (metadata' . ($metadataChanged ? ': classification/GIS eligibility/public visibility changed' : '') . ').');

echo json_encode(['success' => true, 'name' => $name]);
