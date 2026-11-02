<?php
/**
 * =====================================================================
 * Print Report — printable page
 * ---------------------------------------------------------------------
 * GET ?report=<key>&<the report page's own filters>. Renders the same
 * payload as api/export-report.php (includes/shared/report_export.php)
 * as a clean, print-only document: maroon HEALTrack header band with
 * the facility name, report title, date generated and the filters in
 * effect, then the summary figures and every table. No sidebar,
 * buttons or filter controls are printed. Table headers repeat on each
 * page, rows never split across pages, and the page footer carries
 * "Page X of Y" (CSS @page margin boxes). Opened in a new tab by
 * assets/js/report-export.js, which adds &autoprint=1 to open the
 * browser's print dialog straight away.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // asset_url()
$_GET['format'] = 'print';
require __DIR__ . '/../includes/shared/report_export_request.php'; // $rxPayload

$e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// For CSS content strings in the @page footer.
$cssString = fn ($s) => '"' . str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ' '], (string) $s) . '"';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($rxPayload['title']) ?> — <?= $e($rxPayload['facility']) ?></title>
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
:root{ --maroon: #690B0B; --text: #1F2937; --muted: #6B7280; --border: #E5E7EB; --soft: #F8F5F5; }
*{ box-sizing: border-box; }
html, body{ margin: 0; background: #EEF0F3; color: var(--text); font: 400 12px/1.45 Poppins, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
.rp-toolbar{ position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; gap: 10px; padding: 12px; background: #fff; border-bottom: 1px solid var(--border); }
.rp-toolbar button{ border: 1px solid var(--border); background: #fff; color: var(--text); border-radius: 8px; padding: 8px 16px; font: 600 13px Poppins, sans-serif; cursor: pointer; }
.rp-toolbar button.primary{ background: var(--maroon); border-color: var(--maroon); color: #fff; }
.rp-sheet{ width: 210mm; max-width: 100%; margin: 20px auto; background: #fff; padding: 14mm 12mm; box-shadow: 0 4px 18px rgba(0,0,0,.08); }

.rp-header{ background: var(--maroon); color: #fff; border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.rp-brand{ display: flex; align-items: center; gap: 12px; }
.rp-logo{ width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.rp-brand h1{ margin: 0; font-size: 18px; font-weight: 700; letter-spacing: .2px; }
.rp-brand p{ margin: 0; font-size: 11px; opacity: .85; }
.rp-facility{ text-align: right; }
.rp-facility strong{ display: block; font-size: 14px; }
.rp-facility span{ font-size: 11px; opacity: .85; }

.rp-title{ margin: 18px 0 4px; font-size: 20px; font-weight: 700; color: var(--maroon); }
.rp-meta{ display: flex; flex-wrap: wrap; gap: 4px 18px; color: var(--muted); font-size: 11px; margin-bottom: 14px; }
.rp-meta b{ color: var(--text); font-weight: 600; }

.rp-filters{ border: 1px solid var(--border); border-left: 4px solid var(--maroon); border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 20px; background: var(--soft); }
.rp-filters div{ font-size: 11px; }
.rp-filters span{ color: var(--muted); }
.rp-filters b{ font-weight: 600; }

h2.rp-section{ font-size: 13px; font-weight: 700; color: var(--maroon); margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 2px solid var(--maroon); break-after: avoid; page-break-after: avoid; }
.rp-summary{ display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.rp-stat{ border: 1px solid var(--border); border-radius: 6px; padding: 8px 10px; break-inside: avoid; }
.rp-stat .label{ font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .3px; }
.rp-stat .value{ font-size: 15px; font-weight: 700; margin: 2px 0; overflow-wrap: anywhere; }
.rp-stat .sub{ font-size: 10px; color: var(--muted); }

.rp-table-block{ margin-bottom: 4px; }
table.rp-table{ width: 100%; border-collapse: collapse; font-size: 10.5px; }
.rp-table thead{ display: table-header-group; }
.rp-table tfoot{ display: table-row-group; }
.rp-table th{ background: var(--maroon); color: #fff; text-align: left; font-weight: 600; padding: 6px 8px; }
.rp-table td{ padding: 5px 8px; border-bottom: 1px solid var(--border); vertical-align: top; overflow-wrap: break-word; }
.rp-table tbody tr:nth-child(even) td{ background: #FAFAFB; }
.rp-table tr{ break-inside: avoid; page-break-inside: avoid; }
.rp-table tfoot td{ font-weight: 700; border-top: 2px solid var(--maroon); background: var(--soft); }
.rp-table .num{ text-align: right; white-space: nowrap; }
.rp-count{ font-weight: 500; color: var(--muted); font-size: 11px; }
.rp-note{ font-size: 10.5px; color: var(--muted); margin: 6px 0 0; }
.rp-empty{ font-size: 11px; color: var(--muted); padding: 10px; border: 1px dashed var(--border); border-radius: 6px; text-align: center; }
.rp-notes{ margin-top: 18px; font-size: 10.5px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 8px; }
.rp-notes p{ margin: 0 0 3px; }

* { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
@page{
  size: A4;
  margin: 14mm 12mm 16mm;
  @bottom-left{ content: <?= $cssString('HEALTrack · ' . $rxPayload['facility'] . ' · ' . $rxPayload['title']) ?>; font: 8pt Poppins, sans-serif; color: #6B7280; }
  @bottom-right{ content: "Page " counter(page) " of " counter(pages); font: 8pt Poppins, sans-serif; color: #6B7280; }
}
@media print{
  html, body{ background: #fff; }
  .rp-toolbar{ display: none !important; }
  .rp-sheet{ width: auto; margin: 0; padding: 0; box-shadow: none; }
}
@media (max-width: 640px){
  .rp-sheet{ padding: 16px; margin: 0; }
  .rp-header{ flex-direction: column; align-items: flex-start; }
  .rp-facility{ text-align: left; }
  .rp-summary, .rp-filters{ grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="rp-toolbar">
    <button type="button" class="primary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
    <button type="button" onclick="window.close()">Close</button>
</div>

<div class="rp-sheet">
    <header class="rp-header">
        <div class="rp-brand">
            <div class="rp-logo"><i class="bi bi-heart-pulse-fill"></i></div>
            <div>
                <h1>HEALTrack</h1>
                <p>Health Information System</p>
            </div>
        </div>
        <div class="rp-facility">
            <strong><?= $e($rxPayload['facility']) ?></strong>
            <span>Analytics &amp; Reports</span>
        </div>
    </header>

    <h1 class="rp-title"><?= $e($rxPayload['title']) ?></h1>
    <div class="rp-meta">
        <span>Date generated: <b><?= $e($rxPayload['generatedAt']) ?></b></span>
        <span>Generated by: <b><?= $e($rxPayload['generatedBy']) ?></b></span>
    </div>

    <div class="rp-filters">
        <?php foreach ($rxPayload['filters'] as [$label, $value]): ?>
        <div><span><?= $e($label) ?>:</span> <b><?= $e($value) ?></b></div>
        <?php endforeach; ?>
    </div>

    <h2 class="rp-section">Summary</h2>
    <div class="rp-summary">
        <?php foreach ($rxPayload['summary'] as [$label, $value, $sub]): ?>
        <div class="rp-stat">
            <div class="label"><?= $e($label) ?></div>
            <div class="value"><?= $e($value) ?></div>
            <?php if ($sub !== ''): ?><div class="sub"><?= $e($sub) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($rxPayload['tables'] as $table): ?>
    <section class="rp-table-block">
        <h2 class="rp-section"><?= $e($table['title']) ?> <span class="rp-count">(<?= number_format(count($table['rows'])) ?> row<?= count($table['rows']) === 1 ? '' : 's' ?>)</span></h2>
        <?php if ($table['rows'] === []): ?>
        <div class="rp-empty"><?= $e($table['note'] ?? 'No records match the selected filters.') ?></div>
        <?php else: ?>
        <table class="rp-table">
            <thead>
                <tr><?php foreach ($table['columns'] as $col): ?><th><?= $e($col) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($table['rows'] as $row): ?>
                <tr><?php foreach ($row as $i => $cell): ?><td class="<?= $i > 0 && preg_match('/^[\d,.]+(%| days?)?$/', (string) $cell) ? 'num' : '' ?>"><?= $e($cell) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (!empty($table['footer'])): ?>
            <tfoot>
                <tr><?php foreach ($table['footer'] as $i => $cell): ?><td class="<?= $i > 0 ? 'num' : '' ?>"><?= $e($cell) ?></td><?php endforeach; ?></tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <?php if (!empty($table['note'])): ?><p class="rp-note"><?= $e($table['note']) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php if ($rxPayload['notes'] !== []): ?>
    <div class="rp-notes">
        <?php foreach ($rxPayload['notes'] as $note): ?><p><?= $e($note) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($_GET['autoprint'])): ?>
<script>
    // Fonts first, so the printed text doesn't fall back mid-layout.
    window.addEventListener('load', function () {
        (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { window.print(); });
    });
</script>
<?php endif; ?>
</body>
</html>
