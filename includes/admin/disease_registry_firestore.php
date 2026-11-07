<?php
/**
 * =====================================================================
 * Disease Registry (Admin) — Data
 * ---------------------------------------------------------------------
 * Backs admin/disease_registry.php — the one real management UI for
 * includes/firestore/disease_registry.php's "disease_registry"
 * collection. Classifying a disease (deciding whether it's safe for the
 * public GIS map) is a real, documented, audited decision — see
 * api/update-disease-classification.php. Routine keyword/name
 * maintenance is a separate, unaudited action (api/update-disease-registry-entry.php)
 * so it never has to fabricate a "reason" just to satisfy that
 * endpoint's no-op guard.
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
// Real registry entries, grouped by classification tier — same real
// Firestore read every GIS/predictive-analytics page already shares
// (hc_get_disease_registry_docs(), self-seeding on first-ever read).
// ---------------------------------------------------------------------
$classificationMeta = [
    'public_awareness' => ['label' => 'Public Awareness', 'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
    'conditional_public_display' => ['label' => 'Conditional Public Display', 'bg' => '#FDF3DC', 'color' => '#B7791F'],
    'internal_surveillance_only' => ['label' => 'Internal Surveillance Only', 'bg' => '#FCE4E4', 'color' => '#D93025'],
    'pending_classification' => ['label' => 'Pending Classification', 'bg' => '#EEF0F3', 'color' => '#6B7280'],
];

$registryRows = [];
foreach (hc_get_disease_registry_docs() as $doc) {
    $f = $doc['fields'];
    $classification = $f['classification'] ?? 'pending_classification';
    $registryRows[] = [
        'id' => $doc['id'],
        'name' => $f['name'] ?? '(unnamed)',
        'keywords' => $f['keywords'] ?? [],
        'classification' => $classification,
        'classificationLabel' => $classificationMeta[$classification]['label'] ?? $classification,
        'isPreventable' => !empty($f['isPreventable']),
        'preventiveMeasures' => $f['preventiveMeasures'] ?? '',
        'isAutoDetected' => !empty($f['isAutoDetected']),
        'classifiedByName' => $f['classifiedByName'] ?? 'System',
        'classifiedAt' => $f['classifiedAt'] ?? '',
        'classificationReason' => $f['classificationReason'] ?? '',
    ];
}
usort($registryRows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$registryByClassification = [
    'public_awareness' => [],
    'conditional_public_display' => [],
    'internal_surveillance_only' => [],
    'pending_classification' => [],
];
foreach ($registryRows as $row) {
    $registryByClassification[$row['classification']][] = $row;
}
$registryTotalCount = count($registryRows);
$pendingCount = count($registryByClassification['pending_classification']);

// ---------------------------------------------------------------------
// Candidate conditions — real recurring diagnosis text that doesn't
// match any registry keyword yet (hc_disease_registry_candidates()).
// ---------------------------------------------------------------------
$medicalRecordDocs = array_values(array_filter(
    hc_get_all_medical_record_docs(),
    fn ($doc) => ($doc['fields']['type'] ?? '') === 'consultation'
));
$candidates = hc_disease_registry_candidates($medicalRecordDocs);
