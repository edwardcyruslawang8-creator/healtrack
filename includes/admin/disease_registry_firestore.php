<?php
/**
 * =====================================================================
 * Disease Registry (Admin) — Data
 * ---------------------------------------------------------------------
 * Backs admin/disease_registry.php — the one real management UI for
 * includes/firestore/disease_registry.php's "disease_registry"
 * collection. Assigning a condition one of the 5 fixed clinical
 * categories (or leaving it uncategorized) is ordinary catalog
 * maintenance, not a gated decision — see
 * api/update-disease-registry-entry.php and that file's own header
 * comment for why there's no separate reason-required endpoint.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/disease_registry.php';
require_once __DIR__ . '/../firestore/medical_records.php'; // hc_get_all_medical_record_docs()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => 'System Administrator',
];

// ---------------------------------------------------------------------
// Real registry entries, grouped by category — same real Firestore
// read every GIS/predictive-analytics page already shares
// (hc_get_disease_registry_docs(), self-seeding on first-ever read).
// 'uncategorized' is a synthetic bucket (no 6th real category exists —
// see hc_disease_registry_category_meta()'s own header comment) for
// any condition with no category assigned yet, which stays excluded
// from the public GIS until one is.
// ---------------------------------------------------------------------
$categoryMeta = hc_disease_registry_category_meta() + [
    'uncategorized' => 'Uncategorized',
];
$categoryColors = [
    'communicable_preventable' => ['bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'respiratory' => ['bg' => '#E3F0FC', 'color' => '#1A73E8'],
    'environmental_sanitation' => ['bg' => '#FDF3DC', 'color' => '#B7791F'],
    'animal_bite' => ['bg' => '#FDE8DC', 'color' => '#C2560C'],
    'other_approved_prevention' => ['bg' => '#F1E7FB', 'color' => '#7E3FB5'],
    'uncategorized' => ['bg' => '#EEF0F3', 'color' => '#6B7280'],
];

$registryRows = [];
foreach (hc_get_disease_registry_docs() as $doc) {
    $f = $doc['fields'];
    $category = $f['category'] ?? null;
    $categoryKey = $category ?? 'uncategorized';
    $registryRows[] = [
        'id' => $doc['id'],
        'name' => $f['name'] ?? '(unnamed)',
        'keywords' => $f['keywords'] ?? [],
        'category' => $category,
        'categoryKey' => $categoryKey,
        'categoryLabel' => $categoryMeta[$categoryKey] ?? $categoryKey,
        'isPreventable' => !empty($f['isPreventable']),
        'preventiveMeasures' => $f['preventiveMeasures'] ?? '',
        'isAutoDetected' => !empty($f['isAutoDetected']),
        'updatedByName' => $f['updatedByName'] ?? 'System',
        'updatedAt' => $f['updatedAt'] ?? '',
    ];
}
usort($registryRows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$registryByCategory = array_fill_keys(array_keys($categoryMeta), []);
foreach ($registryRows as $row) {
    $registryByCategory[$row['categoryKey']][] = $row;
}
$registryTotalCount = count($registryRows);
$pendingCount = count($registryByCategory['uncategorized']);

// ---------------------------------------------------------------------
// Candidate conditions — real recurring diagnosis text that doesn't
// match any registry keyword yet (hc_disease_registry_candidates()).
// ---------------------------------------------------------------------
$medicalRecordDocs = array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => ($doc['fields']['type'] ?? '') === 'consultation'
));
$candidates = hc_disease_registry_candidates($medicalRecordDocs);
