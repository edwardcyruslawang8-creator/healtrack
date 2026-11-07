<?php
/**
 * =====================================================================
 * Add Disease Registry Entry (api/add-disease-registry-entry.php)
 * ---------------------------------------------------------------------
 * Creates a new disease_registry entry — either promoted from
 * admin/disease_registry.php's "Candidate Conditions" list (a real,
 * recurring diagnosis string nothing in the registry matches yet — see
 * hc_disease_registry_candidates()) or typed from scratch.
 *
 * Admin-only (system-wide scope). Server FORCES a brand-new entry to
 * start with NO category, ignoring any category value the client
 * sends — "newly recognized diseases must not automatically become
 * publicly visible" holds even against a tampered request. A category
 * is assigned afterward, as ordinary maintenance, via
 * api/update-disease-registry-entry.php.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
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
$keywordsRaw = (string) ($body['keywords'] ?? '');
$isPreventable = !empty($body['isPreventable']);
$isAutoDetected = !empty($body['isAutoDetected']); // true when promoted from a real candidate; false when typed from scratch

if ($name === '') {
    adre_fail(422, 'Please enter a condition name.');
}

$keywords = array_values(array_unique(array_filter(
    array_map(fn ($k) => strtolower(trim($k)), explode(',', $keywordsRaw)),
    fn ($k) => $k !== ''
)));
if (empty($keywords)) {
    adre_fail(422, 'Please enter at least one match keyword.');
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
    'keywords' => $keywords,
    'category' => null, // always — never accepts a client-supplied category, see this file's own header comment
    'isPreventable' => $isPreventable,
    'preventiveMeasures' => '',
    'isAutoDetected' => $isAutoDetected,
    'createdAt' => $nowIso,
    'updatedByName' => $adminName,
    'updatedByUid' => $adminUid,
    'updatedByRole' => 'admin',
    'updatedAt' => $nowIso,
    'history' => [[
        'date' => $today,
        'event' => 'Created — no category yet (excluded from public GIS)',
        'previousCategory' => null,
        'newCategory' => null,
        'changedByName' => $adminName,
        'changedByUid' => $adminUid,
        'changedByRole' => 'admin',
        'recordedAt' => $nowIso,
    ]],
]);

if ($newId === null) {
    adre_fail(500, 'Could not create this entry. Please try again.');
}

write_audit_log('created', 'Disease Registry', 'Added "' . $name . '" to the disease registry (uncategorized).');

echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
