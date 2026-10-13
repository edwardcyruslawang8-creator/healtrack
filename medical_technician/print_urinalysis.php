<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Print: Urinalysis Form (medical_technician/print_urinalysis.php)
 * ---------------------------------------------------------------------
 * Opened in a new tab from urinalysis_form.php's "Print Form" button.
 * Standalone print layout (no sidebar/topbar) matching the real Lipa
 * City Health Office "URINALYSIS" paper form as closely as this app
 * can reproduce without the office's actual seal artwork (no image
 * asset for it exists anywhere in this project — the header prints as
 * text only; drop the seal image into assets/images/ and add an <img>
 * tag here if one becomes available).
 *
 * Reads whatever's really in Firestore right now for this patient's
 * Urinalysis doc (includes/medical_technician/urinalysis_form_firestore.php,
 * same data file urinalysis_form.php uses) — a completed test prints
 * its real saved values, a still-pending one prints blank, exactly
 * like handing over a partially-filled paper slip.
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

$isEmbedded = ($_GET['embed'] ?? '') === '1';
$todayLabel = date('F j, Y');
$physicalFields = hc_urinalysis_physical_fields();
$microscopicFields = hc_urinalysis_microscopic_fields();
$microscopicKeys = array_keys($microscopicFields);
$microscopicLeft = array_slice($microscopicKeys, 0, 4);
$microscopicRight = array_slice($microscopicKeys, 4, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Urinalysis Form — Print</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Arial', 'Helvetica', sans-serif;
    color: #111;
    max-width: 820px;
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
  .prt-title { text-align: center; margin-bottom: 18px; }
  .prt-title .main { font-size: 17px; font-weight: 800; text-decoration: underline; }
  .prt-section-label { font-size: 12.5px; font-weight: 700; margin: 16px 0 6px; text-transform: uppercase; }
  .prt-physical { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .prt-physical th, .prt-physical td {
    border: 1px solid #111; padding: 8px 6px; font-size: 11.5px; text-align: center;
  }
  .prt-physical th { font-weight: 700; }
  .prt-physical td { height: 26px; }
  .prt-microscopic { width: 100%; border-collapse: collapse; margin-bottom: 60px; }
  .prt-microscopic td { padding: 7px 4px; font-size: 12.5px; border-bottom: 1px solid #ccc; }
  .prt-microscopic td.label { font-weight: 700; width: 44%; }
  .prt-microscopic td.value { border-bottom: 1px solid #111; }
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
<p style="text-align:center;">No Urinalysis order found for this patient.</p>
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
    <div class="main">URINALYSIS</div>
</div>

<div class="prt-section-label">Physical Examination</div>
<table class="prt-physical">
    <thead>
        <tr>
            <?php foreach ($physicalFields as $label): ?>
            <th><?= htmlspecialchars($label) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <?php foreach ($physicalFields as $key => $label): ?>
            <td><?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '1', $key, $physicalFields)) ?></td>
            <?php endforeach; ?>
        </tr>
    </tbody>
</table>

<div class="prt-section-label">Microscopic Examination</div>
<table class="prt-microscopic">
    <?php foreach ($microscopicLeft as $i => $leftKey): $rightKey = $microscopicRight[$i]; ?>
    <tr>
        <td class="label"><?= htmlspecialchars($microscopicFields[$leftKey]) ?></td>
        <td class="value"><?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '2', $leftKey, $microscopicFields)) ?></td>
        <td class="label"><?= htmlspecialchars($microscopicFields[$rightKey]) ?></td>
        <td class="value"><?= htmlspecialchars(hc_urinalysis_saved_value($currentDoc, '2', $rightKey, $microscopicFields)) ?></td>
    </tr>
    <?php endforeach; ?>
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
