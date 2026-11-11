<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: CBC Form (medical_technician/print_cbc.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from cbc_form.php's "Print Form" button. Uses
 * the one uniform lab report template every test shares
 * (includes/shared/lab_result_report.php's hc_lab_result_report_css()/
 * hc_lab_result_report_body_html() — same template
 * api/download-lab-result-attachment.php serves for a completed test's
 * stored "Attached File") — only the Results table's own 15 rows
 * (8 Hematology + 5 Differential Count + Blood Type/Rh —
 * hc_cbc_hematology_fields()/hc_cbc_differential_fields(),
 * includes/firestore/lab_results.php) are specific to this test, same
 * reasoning as the other 6 built-in tests' own copy of this page.
 *
 * Reads whatever's really in Firestore right now for this patient's
 * CBC doc (includes/medical_technician/cbc_form_firestore.php, same
 * data file cbc_form.php uses) — a completed test prints its real
 * saved values; a still-pending one prints the same 15 rows blank,
 * exactly like handing over a partially-filled paper slip.
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

require_once __DIR__ . '/../includes/medical_technician/cbc_form_firestore.php';
// $patient, $currentDoc
require_once __DIR__ . '/../includes/shared/lab_result_report.php';

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$hematologyFields = hc_cbc_hematology_fields();
$differentialFields = hc_cbc_differential_fields();

/** The real saved row (result AND its Low/Normal/High classification) for $label, or a blank row if still pending. */
$savedRow = function (string $label, string $unit, string $range) use ($currentDoc): array {
    foreach (['group1_rows', 'group2_rows', 'group3_rows'] as $key) {
        foreach ($currentDoc['fields']['resultGroups'][$key] ?? [] as $row) {
            if (($row['test'] ?? '') === $label) {
                return $row;
            }
        }
    }
    return ['test' => $label, 'result' => '', 'unit' => $unit, 'range' => $range, 'interpretation' => ''];
};

/** @param array<string, array{label: string, unit: string, range: string}> $fields */
$buildRows = function (array $fields) use ($savedRow): array {
    $rows = [];
    foreach ($fields as $f) {
        $rows[] = $savedRow($f['label'], $f['unit'], $f['range']);
    }
    return $rows;
};

$labFieldsForPrint = [
    'testName' => 'CBC (Complete Blood Count)',
    'interpretation' => $currentDoc['fields']['interpretation'] ?? '',
    'resultGroups' => [
        'group1_name' => 'Hematology',
        'group1_rows' => $buildRows($hematologyFields),
        'group2_name' => 'Differential Count',
        'group2_rows' => $buildRows($differentialFields),
        'group3_name' => 'Blood Typing',
        'group3_rows' => [
            ['test' => 'Blood Type', 'result' => hc_cbc_saved_value($currentDoc, 'bloodType', ['bloodType' => 'Blood Type']), 'unit' => '', 'range' => '', 'interpretation' => ''],
            ['test' => 'Rh', 'result' => hc_cbc_saved_value($currentDoc, 'rh', ['rh' => 'Rh']), 'unit' => '', 'range' => '', 'interpretation' => ''],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>CBC Form — Print</title>
<style><?= hc_lab_result_report_css() ?></style>
</head>
<body>

<div class="prt-toolbar" <?= $isEmbedded ? 'style="display:none;"' : '' ?>>
    <button onclick="window.print()">Print</button>
</div>

<?php if ($patient === null || $currentDoc === null): ?>
<p style="text-align:center;">No CBC order found for this patient.</p>
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
