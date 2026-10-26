<?php
/**
 * =====================================================================
 * Lab Test Catalog — Data
 * ---------------------------------------------------------------------
 * Backs medical_technician/lab_test_catalog.php — lets a medical
 * technologist manage the real, live set of lab tests North Super
 * Health Center offers (the "lab_test_catalog" Firestore collection
 * includes/firestore/lab_results.php's hc_lab_test_catalog() now reads
 * from, self-seeded from the app's original hardcoded list the first
 * time it's read — see hc_seed_lab_test_catalog()'s header comment).
 *
 * Each row also shows whether a reference template document has been
 * uploaded for it (Upload Template action — see
 * hc_get_lab_test_template()'s header comment for what that is and
 * isn't used for) — available for every test, including the 6 with
 * their own dedicated, hardcoded result-entry forms (isBuiltIn: true —
 * Urinalysis/CD4/Gram Stain/the 3 Serology Rapid tests). isBuiltIn only
 * locks Delete, since removing one of those 6 would orphan the literal
 * test-name routing their dedicated pages depend on.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/patients.php';
require_once __DIR__ . '/../firestore/lab_results.php';
require_once __DIR__ . '/../firestore/xray_results.php'; // hc_get_pending_lab_count() (sidebar badge) needs both collections
require_once __DIR__ . '/../shared/xray_result_storage.php'; // hc_format_file_size()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Medical Technologist',
    'role'     => 'Medical Technologist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

$dashboardDateLabel = date('F j, Y');

// ---------------------------------------------------------------------
// Catalog rows, grouped by category — same grouping
// hc_lab_test_catalog() itself returns, just carrying the extra id/
// isBuiltIn/template fields a management table (and the Upload
// Template modal) needs that the doctor-facing checkbox panel doesn't.
// ---------------------------------------------------------------------
$catalogByCategory = [];
foreach (hc_get_lab_test_catalog_docs() as $doc) {
    $f = $doc['fields'];
    $templateFileName = $f['templateFileName'] ?? null;
    $catalogByCategory[$f['category'] ?? 'Other'][] = [
        'id' => $doc['id'],
        'testName' => $f['testName'] ?? '—',
        'isBuiltIn' => !empty($f['isBuiltIn']),
        'hasTemplate' => $templateFileName !== null && $templateFileName !== '',
        'templateFileName' => $templateFileName,
        'templateFileSizeLabel' => isset($f['templateFileSize']) ? hc_format_file_size((int) $f['templateFileSize']) : '',
    ];
}
ksort($catalogByCategory);
foreach ($catalogByCategory as $category => $rows) {
    usort($catalogByCategory[$category], fn ($a, $b) => strcasecmp($a['testName'], $b['testName']));
}

$catalogTotalCount = array_sum(array_map('count', $catalogByCategory));
