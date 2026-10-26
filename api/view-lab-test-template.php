<?php
/**
 * =====================================================================
 * View Lab Test Template (api/view-lab-test-template.php)
 * ---------------------------------------------------------------------
 * Called by the "View Template" link on medical_technician/lab_test_catalog.php
 * and on any page where a medical technologist fills in that test's
 * result (medical_technician/lab_result_form.php, and the 4 dedicated
 * pages for a built-in test). Unlike api/view-profile-photo.php (only
 * ever the signed-in user's OWN photo), this streams back ANY test's
 * template by its real catalog doc id — there's no per-uploader
 * ownership here, it's a shared reference document for the whole
 * facility's catalog, so only the role is checked.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/firestore/lab_results.php';
require_once __DIR__ . '/../includes/shared/lab_test_template_storage.php';

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    http_response_code(403);
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(404);
    exit;
}

$target = null;
foreach (hc_get_lab_test_catalog_docs() as $doc) {
    if ($doc['id'] === $id) {
        $target = $doc;
        break;
    }
}

$ext = $target['fields']['templateFileExt'] ?? null;
$fileName = $target['fields']['templateFileName'] ?? null;
$filePath = $ext !== null ? hc_lab_test_template_storage_dir() . '/' . $id . '.' . $ext : '';

if ($target === null || $ext === null || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . hc_lab_test_template_mime_for_extension($ext));
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . addslashes((string) $fileName) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
