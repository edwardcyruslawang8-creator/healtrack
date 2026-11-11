<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: Gram Stain Form (medical_technician/print_gram_stain.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from gram_stain_form.php's "Print Form" button.
 * Uses the one uniform lab report template every test shares
 * (includes/shared/lab_result_report.php's hc_lab_result_report_css()/
 * hc_lab_result_report_body_html() — same template
 * api/download-lab-result-attachment.php serves for a completed test's
 * stored "Attached File") — only the Results table's own rows are
 * specific to Gram Stain (one "Gram Stain" row), same reasoning as the
 * other 3 built-in tests' own copy of this page.
 *
 * Reads whatever's really in Firestore right now for this patient's
 * Gram Stain doc (includes/medical_technician/gram_stain_form_firestore.php,
 * same data file gram_stain_form.php uses) — a completed test prints
 * its real saved Specimen/Result; a still-pending one prints the same
 * "Gram Stain" row blank, exactly like handing over a partially-filled
 * paper slip.
 *
 * ?embed=1 — doctor/laboratory_result_details.php embeds this exact
 * page in an <iframe> on its left column so a doctor sees the real
 * paper-form layout, not just a plain field list. Suppresses the
 * auto window.print() trigger and the on-screen Print button in that
 * mode (an iframe silently popping the browser print dialog would be
 * a bad surprise) — everything else renders identically either way.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (!in_array($_SESSION['role'] ?? null, ['medical_technician', 'doctor'], true)) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/gram_stain_form_firestore.php';
// $patient, $currentDoc, $staffProfile
require_once __DIR__ . '/../includes/shared/lab_result_report.php';

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$savedResult = hc_gram_stain_saved_value($currentDoc, 'result');

// The Results table's row for THIS test — real values once completed
// (the doc's own resultGroups, already in this exact shape), blank
// with the right row label while still pending.
$labFieldsForPrint = [
    'testName' => 'Gram Stain',
    'interpretation' => $savedResult,
    'resultGroups' => [
        'group1_name' => 'Gram Stain',
        'group1_rows' => [[
            'test' => 'Gram Stain',
            'result' => $savedResult,
            'unit' => '',
            'range' => '',
            'interpretation' => '',
        ]],
        'group2_name' => null,
        'group2_rows' => [],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>Gram Stain Form — Print</title>
<style><?= hc_lab_result_report_css() ?></style>
</head>
<body>

<div class="prt-toolbar" <?= $isEmbedded ? 'style="display:none;"' : '' ?>>
    <button onclick="window.print()">Print</button>
</div>

<?php if ($patient === null || $currentDoc === null): ?>
<p style="text-align:center;">No Gram Stain order found for this patient.</p>
<?php else: ?>
<?= hc_lab_result_report_body_html($patient, $labFieldsForPrint, $currentDoc['fields']['assignedToName'] ?? 'Medical Technologist') ?>
<?php endif; ?>

<?php if (!$isEmbedded): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
});
</script>
<?php endif; ?>
</body>
</html>
