<?php
/**
 * =====================================================================
 * Disease Registry (Admin) — Data
 * ---------------------------------------------------------------------
 * Backs admin/disease_registry.php — the one real management UI for
 * includes/shared/clinical_classification.php's "disease_registry"
 * Firestore collection. Assigning a condition a classification, GIS
 * eligibility, public visibility, or its preventive-measures text is
 * ordinary disease-level metadata upkeep, not a per-case decision — see
 * that file's own header comment. Every real audit entry (who changed
 * what, when) lives in each doc's own history[] array, written by
 * api/update-disease-registry-entry.php.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/clinical_classification.php';

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Administrator',
    'role' => 'System Administrator',
];

// ---------------------------------------------------------------------
// Real registry entries, grouped by classification — same real
// Firestore read every GIS/predictive-analytics page already shares
// (hc_get_disease_registry_docs(), self-seeding/migrating on first
// read). 'unclassified' is a synthetic bucket for any condition with
// no classification yet (including auto-provisioned ones) — stays
// excluded from GIS/public display until classified.
// ---------------------------------------------------------------------
$classificationMeta = hc_disease_registry_classification_meta() + [
    'unclassified' => 'Unclassified',
];
$classificationColors = [
    'vector_borne' => ['bg' => '#FDE8DC', 'color' => '#C2560C'],
    'waterborne_foodborne' => ['bg' => '#FDF3DC', 'color' => '#B7791F'],
    'respiratory_contact' => ['bg' => '#E3F0FC', 'color' => '#1A73E8'],
    'environment_zoonotic' => ['bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'animal_exposure' => ['bg' => '#F1E7FB', 'color' => '#7E3FB5'],
    'unclassified' => ['bg' => '#EEF0F3', 'color' => '#6B7280'],
];

$registryRows = [];
foreach (hc_get_disease_registry_docs() as $doc) {
    $f = $doc['fields'];
    $classification = $f['classification'] ?? null;
    $classKey = $classification ?? 'unclassified';
    $registryRows[] = [
        'id' => $doc['id'],
        'name' => $f['name'] ?? '(unnamed)',
        'synonyms' => $f['synonyms'] ?? [],
        'classification' => $classification,
        'classKey' => $classKey,
        'classificationLabel' => $classificationMeta[$classKey] ?? $classKey,
        'registryStatus' => $f['registryStatus'] ?? 'unclassified',
        'gisEligible' => !empty($f['gisEligible']),
        'publicVisibility' => !empty($f['publicVisibility']),
        'isPreventable' => !empty($f['isPreventable']),
        'behavioralMeasures' => $f['behavioralMeasures'] ?? '',
        'environmentalMeasures' => $f['environmentalMeasures'] ?? '',
        'communityMeasures' => $f['communityMeasures'] ?? '',
        'isAutoDetected' => !empty($f['isAutoDetected']),
        'updatedByName' => $f['updatedByName'] ?? 'System',
        'updatedAt' => $f['updatedAt'] ?? '',
    ];
}
usort($registryRows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$registryByClassification = array_fill_keys(array_keys($classificationMeta), []);
foreach ($registryRows as $row) {
    $registryByClassification[$row['classKey']][] = $row;
}
$registryTotalCount = count($registryRows);
$unclassifiedCount = count($registryByClassification['unclassified']);
