<?php
/**
 * =====================================================================
 * Consultations by Barangay Card — Partial
 * ---------------------------------------------------------------------
 * Extracted out of subadmin/consultation_reports.php so the exact same
 * markup can be rendered twice: once on a normal full page load, and
 * once as a small HTML fragment by api/consultation-reports-barangay-table.php
 * (assets/js/cr-barangay-pagination.js's AJAX First/Previous/
 * page-number/Next/Last handler) — so paging through this one card
 * never has to reload the whole Consultation Reports page (the stat
 * cards, both donuts, the trend chart, and the Consultations by
 * Physician table next to it all stay exactly as they were), and the
 * two render paths can never drift out of sync with each other.
 *
 * Expects $consultationsByBarangay, $consultationsByBarangayTotal,
 * $consultationsByBarangayCurrentPage, and
 * $consultationsByBarangayTotalPages to already be set (by
 * includes/subadmin/consultation_reports_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card" id="consultationsByBarangayCard">
    <div class="adm-card-header"><h2>Consultations by Barangay (Top 5)</h2></div>
    <div class="adm-table-wrap">
        <table class="adm-table cr-compact-table" style="min-width:0;" data-sortable="true">
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total</th>
                    <th data-no-sort>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultationsByBarangay as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['barangay']) ?></td>
                    <td><?= number_format($b['total']) ?></td>
                    <td>
                        <div class="cr-pct-bar-wrap">
                            <div class="cr-pct-bar-track">
                                <div class="cr-pct-bar-fill" style="width:<?= round($b['pct'] / $consultationsByBarangay[0]['pct'] * 100) ?>%;"></div>
                            </div>
                            <span class="cr-pct-value"><?= rtrim(rtrim(number_format($b['pct'], 1), '0'), '.') ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text">
            <?php if ($consultationsByBarangayTotal > 0): $shownFrom = ($consultationsByBarangayCurrentPage - 1) * 5 + 1; $shownTo = $shownFrom + count($consultationsByBarangay) - 1; ?>
            Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($consultationsByBarangayTotal) ?> entries
            <?php else: ?>
            No entries to show
            <?php endif; ?>
        </span>
        <?= hc_render_pagination_nav('Consultations by barangay pages', 'brgyPage', $consultationsByBarangayCurrentPage, $consultationsByBarangayTotalPages) ?>
    </div>
</div>
