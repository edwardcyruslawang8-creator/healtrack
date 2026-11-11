<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: Urinalysis Form (medical_technician/print_urinalysis.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from urinalysis_form.php's "Print Form" button.
 * Uses the one uniform lab report template every test shares
 * (includes/shared/lab_result_report.php's hc_lab_result_css()/
 * hc_lab_result_report_body_html() — same template
 * api/download-lab-result-attachment.php serves for a completed test's
 * stored "Attached File") — only the Results table's own rows are
 * specific to Urinalysis (Physical Examination's 6 fields +
 * Microscopic Examination's 8 fields — hc_urinalysis_physical_fields()/
 * hc_urinalysis_microscopic_fields()), same reasoning as the other 3
 * built-in tests' own copy of this page.
 *
 * Reads whatever's really in Firestore right now for this patient's
 * Urinalysis doc (includes/medical_technician/urinalysis_form_firestore.php,
 * same data file urinalysis_form.php uses) — a completed test prints
 * its real saved values; a still-pending one prints the same 14 rows
 * blank, exactly like handing over a partially-filled paper slip.
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

require_once __DIR__ . '/../includes/medical_technician/urinalysis_form_firestore.php';
// $patient, $currentDoc
require_once __DIR__ . '/../includes/shared/lab_result_report.php';

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$physicalFields = hc_urinalysis_physical_fields();
$microscopicFields = hc_urinalysis_microscopic_fields();

/** @param array<string,string> $fieldLabels */
$buildRows = function (array $fieldLabels, string $groupNum) use ($currentDoc): array {
    $rows = [];
    foreach ($fieldLabels as $key => $label) {
        $rows[] = ['test' => $label, 'result' => hc_urinalysis_saved_value($currentDoc, $groupNum, $key, $fieldLabels), 'unit' => '', 'range' => '', 'interpretation' => ''];
    }
    return $rows;
};

$labFieldsForPrint = [
    'testName' => 'Urinalysis',
    'interpretation' => $currentDoc['fields']['interpretation'] ?? '',
    'resultGroups' => [
        'group1_name' => 'Physical Examination',
        'group1_rows' => $buildRows($physicalFields, '1'),
        'group2_name' => 'Microscopic Examination',
        'group2_rows' => $buildRows($microscopicFields, '2'),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>Urinalysis Form — Print</title>
<style><?= hc_lab_result_report_css() ?></style>
</head>
<body>

<div class="prt-toolbar" <?= $isEmbedded ? 'style="display:none;"' : '' ?>>
    <button onclick="window.print()">Print</button>
</div>

<?php if ($patient === null || $currentDoc === null): ?>
<p style="text-align:center;">No Urinalysis order found for this patient.</p>
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
