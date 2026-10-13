<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: CD4 Count Form (medical_technician/print_cd4_count.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from cd4_count_form.php's "Print Form" button.
 * Standalone print layout (no sidebar/topbar) matching the real Lipa
 * City Health Office "CD4 COUNT" paper form as closely as this app can
 * reproduce without the office's actual seal artwork (no image asset
 * for it exists anywhere in this project — the header prints as text
 * only; drop the seal image into assets/images/ and add an <img> tag
 * here if one becomes available).
 *
 * Reads whatever's really in Firestore right now for this patient's
 * CD4 doc (includes/medical_technician/cd4_count_form_firestore.php,
 * same data file cd4_count_form.php uses) — a completed test prints
 * its real saved result, a still-pending one prints blank, exactly
 * like handing over a partially-filled paper slip. Specimen and
 * reference range are the paper form's own fixed constants
 * (hc_cd4_specimen_label()/hc_cd4_reference_range()), not per-patient
 * data.
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

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$todayLabel = date('F j, Y');
$referenceRange = hc_cd4_reference_range();
$savedResult = hc_cd4_saved_result($currentDoc);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CD4 Count Form — Print</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Arial', 'Helvetica', sans-serif;
    color: #111;
    max-width: 720px;
    margin: 32px auto;
    padding: 0 24px;
  }
  .prt-toolbar { text-align: center; margin-bottom: 24px; }
  .prt-toolbar button {
    background: #7a0d0d; color: #fff; border: none; border-radius: 8px;
    padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer;
  }
  .prt-header { text-align: center; margin-bottom: 22px; }
  .prt-header h1 { font-size: 20px; font-weight: 800; margin: 0 0 2px; letter-spacing: 0.5px; }
  .prt-header h2 { font-size: 13px; font-weight: 600; margin: 0; letter-spacing: 0.5px; }
  .prt-info-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
  .prt-info-table td {
    border: 1px solid #111; padding: 8px 10px; font-size: 13px; vertical-align: bottom;
  }
  .prt-info-table td span { display: block; font-size: 10px; color: #444; margin-bottom: 10px; }
  .prt-title { text-align: center; margin-bottom: 22px; }
  .prt-title .main { font-size: 17px; font-weight: 800; text-decoration: underline; }
  .prt-result-table { width: 100%; border-collapse: collapse; margin-bottom: 60px; }
  .prt-result-table td { border: 1px solid #111; padding: 12px; font-size: 13px; vertical-align: top; }
  .prt-result-table td.label { font-weight: 700; width: 30%; }
  .prt-result-value { font-size: 16px; font-weight: 700; border-bottom: 1px solid #111; display: inline-block; min-width: 160px; padding-bottom: 4px; margin-bottom: 6px; }
  .prt-range { font-size: 11.5px; color: #444; }
  .prt-sign { text-align: right; }
  .prt-sign .line { display: inline-block; width: 220px; border-top: 1px solid #111; padding-top: 4px; font-size: 12.5px; }
  @media print {
    .prt-toolbar { display: none; }
    body { margin: 0; }
  }
</style>
</head>
<body>

<div class="prt-toolbar" <?= $isEmbedded ? 'style="display:none;"' : '' ?>>
    <button onclick="window.print()">Print</button>
</div>

<?php if ($patient === null || $currentDoc === null): ?>
<p style="text-align:center;">No CD4 Count order found for this patient.</p>
<?php else: ?>

<div class="prt-header">
    <h1>LIPA CITY HEALTH OFFICE</h1>
    <h2>LABORATORY DEPARTMENT</h2>
</div>

<table class="prt-info-table">
    <tr>
        <td style="width:65%;"><span>Name</span><?= htmlspecialchars($patient['name']) ?></td>
        <td><span>Date</span><?= htmlspecialchars($todayLabel) ?></td>
    </tr>
    <tr>
        <td><span>Address</span><?= htmlspecialchars($patient['address']) ?></td>
        <td>
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="border:none; padding:0;"><span>Age</span><?= (int) $patient['age'] ?></td>
                    <td style="border:none; padding:0;"><span>Sex</span><?= htmlspecialchars($patient['gender']) ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="prt-title">
    <div class="main">CD4 COUNT</div>
</div>

<table class="prt-result-table">
    <tr>
        <td class="label">SPECIMEN:</td>
        <td><?= htmlspecialchars(hc_cd4_specimen_label()) ?></td>
    </tr>
    <tr>
        <td class="label">RESULT:</td>
        <td>
            <div class="prt-result-value"><?= $savedResult !== '' ? htmlspecialchars($savedResult) . ' c/uL' : '' ?></div>
            <div class="prt-range">( <?= htmlspecialchars($referenceRange['label']) ?> )</div>
        </td>
    </tr>
</table>

<div class="prt-sign">
    <span class="line">Medical Technologist</span>
</div>

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
