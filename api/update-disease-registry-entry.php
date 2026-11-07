<?php
/**
 * =====================================================================
 * Update Disease Registry Entry (api/update-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Routine catalog maintenance — name, keywords, isPreventable, and
 * which of the 5 fixed clinical categories (if any) the condition
 * belongs to. Assigning a category is ordinary registry upkeep, not a
 * gated decision — see includes/firestore/disease_registry.php's
 * header comment for why there's no separate reason-required
 * classification endpoint any more.
 *
 * Admin-only — same system-wide scope as every other Disease Registry
 * action (see includes/firestore/disease_registry.php's header
 * comment for why this isn't subadmin-manageable).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/disease_registry.php'; // hc_disease_registry_category_meta()
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
$category = trim((string) ($body['category'] ?? ''));
$preventiveMeasures = mb_substr(trim((string) ($body['preventiveMeasures'] ?? '')), 0, 1000);

if ($id === '') {
    udre_fail(422, 'Missing registry entry.');
}
if ($name === '') {
    udre_fail(422, 'Please enter a condition name.');
}
if ($category !== '' && !array_key_exists($category, hc_disease_registry_category_meta())) {
    udre_fail(422, 'Please select a valid category.');
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

$prevCategory = $existing['category'] ?? null;
$newCategory = $category !== '' ? $category : null;
$adminName = $_SESSION['name'] ?? 'Administrator';
$adminUid = $_SESSION['uid'] ?? '';
$nowIso = date('c');

// setDocument() is a full overwrite — copy every existing field
// forward, only replace the fields this form actually edits.
$updated = $existing;
$updated['name'] = $name;
$updated['keywords'] = $keywords;
$updated['isPreventable'] = $isPreventable;
$updated['category'] = $newCategory;
$updated['preventiveMeasures'] = $preventiveMeasures;
$updated['updatedByName'] = $adminName;
$updated['updatedByUid'] = $adminUid;
$updated['updatedByRole'] = 'admin';
$updated['updatedAt'] = $nowIso;

if ($prevCategory !== $newCategory) {
    $categoryMeta = hc_disease_registry_category_meta();
    $updated['history'] = array_values(is_array($existing['history'] ?? null) ? $existing['history'] : []);
    $updated['history'][] = [
        'date' => date('Y-m-d'),
        'event' => 'Category changed: ' . ($categoryMeta[$prevCategory] ?? 'None') . ' → ' . ($categoryMeta[$newCategory] ?? 'None'),
        'previousCategory' => $prevCategory,
        'newCategory' => $newCategory,
        'changedByName' => $adminName,
        'changedByUid' => $adminUid,
        'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ];
}

if (!$firestore->setDocument('disease_registry', $id, $updated)) {
    udre_fail(500, 'Could not save. Please try again.');
}

write_audit_log('updated', 'Disease Registry', 'Updated "' . $name . '" (name/keywords/category).');

echo json_encode(['success' => true, 'name' => $name, 'keywords' => $keywords]);
