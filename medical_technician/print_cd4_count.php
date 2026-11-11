<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: CD4 Count Form (medical_technician/print_cd4_count.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from cd4_count_form.php's "Print Form" button.
 * Uses the one uniform lab report template every test shares
 * (includes/shared/lab_result_report.php's hc_lab_result_report_css()/
 * hc_lab_result_report_body_html() — same template
 * api/download-lab-result-attachment.php serves for a completed test's
 * stored "Attached File") — only the Results table's own row is
 * specific to CD4 Count (one "CD4 Count" row, fixed Specimen/Reference
 * Range constants — hc_cd4_specimen_label()/hc_cd4_reference_range(),
 * not per-patient data), same reasoning as the other 3 built-in tests'
 * own copy of this page.
 *
 * Reads whatever's really in Firestore right now for this patient's
 * CD4 doc (includes/medical_technician/cd4_count_form_firestore.php,
 * same data file cd4_count_form.php uses) — a completed test prints
 * its real saved result; a still-pending one prints the same "CD4
 * Count" row blank, exactly like handing over a partially-filled paper
 * slip.
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

require_once __DIR__ . '/../includes/medical_technician/cd4_count_form_firestore.php';
// $patient, $currentDoc
require_once __DIR__ . '/../includes/shared/lab_result_report.php';

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$referenceRange = hc_cd4_reference_range();
$savedResult = hc_cd4_saved_result($currentDoc);

// The Results table's row for THIS test — real value once completed,
// blank with the right row label/fixed range while still pending.
$labFieldsForPrint = [
    'testName' => 'CD4 Count',
    'interpretation' => $savedResult !== '' ? $savedResult . ' c/uL (reference ' . $referenceRange['label'] . ')' : '',
    'resultGroups' => [
        'group1_name' => 'CD4 Count',
        'group1_rows' => [[
            'test' => 'CD4 Count',
            'result' => $savedResult,
            'unit' => 'c/uL',
            'range' => $referenceRange['label'],
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
<title>CD4 Count Form — Print</title>
<style><?= hc_lab_result_report_css() ?></style>
</head>
<body>

<div class="prt-toolbar" <?= $isEmbedded ? 'style="display:none;"' : '' ?>>
    <button onclick="window.print()">Print</button>
</div>

<?php if ($patient === null || $currentDoc === null): ?>
<p style="text-align:center;">No CD4 Count order found for this patient.</p>
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
