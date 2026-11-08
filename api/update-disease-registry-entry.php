<?php
/**
 * =====================================================================
 * Update Disease Registry Entry (api/update-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Disease-level metadata upkeep — name, synonyms, preventable flag,
 * and the 3 preventive-measure fields. Admin-only (system-wide scope
 * — see includes/shared/clinical_classification.php's header comment
 * for why this isn't subadmin-manageable).
 *
 * Saving ANY entry through this endpoint (including a previously
 * auto-detected/unclassified one) makes it registryStatus='classified',
 * gisEligible=true, publicVisibility=true unconditionally — there is
 * no separate classification/eligibility step any more; the admin UI
 * shows a reminder before this happens (includes/admin/manage_disease_registry_modal.php's
 * #mdrAddReminder). The condition's `classification` field (used only
 * for the GIS dynamic classification filter) is left untouched — this
 * form has no control for it.
 *
 * Deliberately NOT a per-case approval endpoint — it only ever touches
 * a condition's registry metadata, never an individual patient's
 * consultation/diagnosis record. No "reason" is required (this is
 * ordinary catalog upkeep, same as fixing a typo), but a real
 * gisEligible/publicVisibility transition is still appended to the
 * doc's own history[] (the audit log requirement).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/shared/clinical_classification.php'; // hc_normalize_text()
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
$behavioralMeasures = mb_substr(trim((string) ($body['behavioralMeasures'] ?? '')), 0, 500);
$environmentalMeasures = mb_substr(trim((string) ($body['environmentalMeasures'] ?? '')), 0, 500);
$communityMeasures = mb_substr(trim((string) ($body['communityMeasures'] ?? '')), 0, 500);

if ($id === '') {
    udre_fail(422, 'Missing registry entry.');
}
if ($name === '') {
    udre_fail(422, 'Please enter a condition name.');
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

$wasGisEligible = !empty($existing['gisEligible']);
$wasPublicVisibility = !empty($existing['publicVisibility']);
$wasRegistryStatus = $existing['registryStatus'] ?? 'unclassified';

$adminName = $_SESSION['name'] ?? 'Administrator';
$adminUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');

// setDocument() is a full overwrite — copy every existing field
// forward, only replace the fields this form actually edits.
// 'classification' is deliberately left untouched (no control for it
// here) — the registry collection fields on disk aren't deleted just
// because the admin UI no longer exposes them.
$updated = $existing;
$updated['name'] = $name;
$updated['synonyms'] = $synonyms;
$updated['isPreventable'] = $isPreventable;
$updated['registryStatus'] = 'classified';
$updated['gisEligible'] = true;
$updated['publicVisibility'] = true;
$updated['behavioralMeasures'] = $behavioralMeasures;
$updated['environmentalMeasures'] = $environmentalMeasures;
$updated['communityMeasures'] = $communityMeasures;
$updated['updatedByName'] = $adminName;
$updated['updatedByUid'] = $adminUid;
$updated['updatedByRole'] = 'admin';
$updated['updatedAt'] = $nowIso;

$becamePublic = $wasRegistryStatus !== 'classified' || !$wasGisEligible || !$wasPublicVisibility;

if ($becamePublic) {
    $updated['history'] = array_values(is_array($existing['history'] ?? null) ? $existing['history'] : []);
    $updated['history'][] = [
        'date' => date('Y-m-d'),
        'event' => 'Became visible on GIS Mapping (registryStatus/gisEligible/publicVisibility all set true via admin save)',
        'previousGisEligible' => $wasGisEligible, 'newGisEligible' => true,
        'previousPublicVisibility' => $wasPublicVisibility, 'newPublicVisibility' => true,
        'changedByName' => $adminName, 'changedByUid' => $adminUid, 'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ];
}

if (!$firestore->setDocument('disease_registry', $id, $updated)) {
    udre_fail(500, 'Could not save. Please try again.');
}

write_audit_log('updated', 'Disease Registry', 'Updated "' . $name . '"' . ($becamePublic ? ' — now visible on GIS Mapping' : '') . '.');

echo json_encode(['success' => true, 'name' => $name]);
