<?php
/**
 * =====================================================================
 * Report Export Actions (partial)
 * ---------------------------------------------------------------------
 * The Print / PDF / Excel buttons in each Analytics & Reports page's
 * filter bar. assets/js/report-export.js handles the clicks (delegated,
 * so they keep working after assets/js/ajax-report-nav.js swaps <main>)
 * and always sends the page's CURRENT query string, so the output is
 * built from exactly the filters on screen — see
 * includes/shared/report_export.php.
 *
 * Include with $reaReport set beforehand (a key of hc_report_export_registry()).
 * =====================================================================
 */
?>
<div class="rea-actions" role="group" aria-label="Print or export this report">
    <button type="button" class="btn btn-light border arp-filter-select rea-btn" data-report-export="print" data-report="<?= htmlspecialchars($reaReport) ?>">
        <i class="bi bi-printer"></i> Print
    </button>
    <button type="button" class="btn btn-light border arp-filter-select rea-btn" data-report-export="pdf" data-report="<?= htmlspecialchars($reaReport) ?>">
        <i class="bi bi-file-earmark-pdf"></i> PDF
    </button>
    <button type="button" class="btn btn-light border arp-filter-select rea-btn" data-report-export="excel" data-report="<?= htmlspecialchars($reaReport) ?>">
        <i class="bi bi-file-earmark-spreadsheet"></i> Excel
    </button>
</div>
