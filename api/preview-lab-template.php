<?php
/**
 * =====================================================================
 * Preview Built-in Lab Template (api/preview-lab-template.php)
 * ---------------------------------------------------------------------
 * Called by the "preview" icon medical_technician/lab_test_catalog.php
 * now shows next to the 6 tests with a built-in print template (Gram
 * Stain, CD4 Count, Urinalysis, HBsAg/Syphilis/HIV) in place of "No
 * template uploaded yet" — see includes/shared/lab_result_report.php's
 * hc_lab_result_report_blank_fields() header comment for why this is a
 * separate, patient-less preview rather than reusing one of the 4 real
 * print_*.php pages (those are always bound to one real patient's own
 * saved values; this is a pure, no-patient look at the template's
 * shape, reached from the catalog's own management table, not from any
 * one patient's record).
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../config/firebase.php'; // $firestore — Gram Stain's own blank-fields branch below never touches it directly, but every other page in this app initializes it unconditionally; skipping it here was observed to crash this server's PHP/Guzzle setup on that one branch specifically
require_once __DIR__ . '/../includes/shared/lab_result_report.php';

if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$testName = trim((string) ($_GET['testName'] ?? ''));
$labFields = $testName !== '' ? hc_lab_result_report_blank_fields($testName) : null;

if ($labFields === null) {
    http_response_code(404);
    echo 'No built-in template for this test.';
    exit;
}

$samplePatient = ['name' => '(Sample Patient Name)', 'address' => '(Sample Address)', 'age' => 0, 'gender' => '(Sex)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>Template Preview — HEALTrack</title>
<style>
<?= hc_lab_result_report_css() ?>
.prt-preview-banner { max-width: 720px; margin: 0 auto 16px; background: #FDECDD; color: #7a3a0d; border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; text-align: center; }
</style>
</head>
<body>
<div class="prt-preview-banner">Preview only — sample data, not a real patient.</div>
<?= hc_lab_result_report_body_html($samplePatient, $labFields, '(Medical Technologist)') ?>
</body>
</html>
