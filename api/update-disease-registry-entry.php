<?php
/**
 * =====================================================================
 * Update Disease Registry Entry (api/update-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Routine catalog maintenance ONLY — name, keywords, and the
 * isPreventable flag. Deliberately separate from
 * api/update-disease-classification.php (the real, audited,
 * reason-required public-visibility decision) so a simple keyword typo
 * fix never has to fabricate a justification, and a classification
 * change's audit trail is never diluted by unrelated keyword edits.
 *
 * Admin-only — same system-wide scope as every other Disease Registry
 * action (see includes/firestore/disease_registry.php's header
 * comment for why this isn't subadmin-manageable).
 *
 * Classification is explicitly never accepted here, even if present in
 * the request body — this endpoint can never be used as a back door to
 * change public visibility.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
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
$keywordsRaw = (string) ($body['keywords'] ?? '');
$isPreventable = !empty($body['isPreventable']);

if ($id === '') {
    udre_fail(422, 'Missing registry entry.');
}
if ($name === '') {
    udre_fail(422, 'Please enter a condition name.');
}

// Comma-separated keywords, normalized (lowercase, trimmed, deduped,
// blanks dropped) — same matching convention every keyword in this
// registry already uses.
$keywords = array_values(array_unique(array_filter(
    array_map(fn ($k) => strtolower(trim($k)), explode(',', $keywordsRaw)),
    fn ($k) => $k !== ''
)));
if (empty($keywords)) {
    udre_fail(422, 'Please enter at least one match keyword.');
}

$existing = $firestore->getDocument('disease_registry', $id);
if ($existing === null) {
    udre_fail(404, 'This registry entry could not be found.');
}

// setDocument() is a full overwrite — copy every existing field
// forward, only replace name/keywords/isPreventable. Classification and
// its own audit fields are never touched here.
$updated = $existing;
$updated['name'] = $name;
$updated['keywords'] = $keywords;
$updated['isPreventable'] = $isPreventable;

if (!$firestore->setDocument('disease_registry', $id, $updated)) {
    udre_fail(500, 'Could not save. Please try again.');
}

write_audit_log('updated', 'Disease Registry', 'Updated name/keywords for "' . $name . '".');

echo json_encode(['success' => true, 'name' => $name, 'keywords' => $keywords]);
