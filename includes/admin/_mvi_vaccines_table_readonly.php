<?php
/**
 * =====================================================================
 * Vaccines Table + Pagination — Partial (Admin, read-only)
 * ---------------------------------------------------------------------
 * Same idea as includes/admin/_mvi_medicines_table_readonly.php — see
 * that file's own header comment.
 * =====================================================================
 */
?>
<div class="adm-card mvi-table-card">
    <div class="adm-table-wrap">
        <table class="adm-table mvi-table">
            <thead>
                <tr>
                    <th>Vaccine Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Minimum Level</th>
                    <th>Reorder Level</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vaccines)): ?>
                <tr>
                    <td colspan="9" class="adm-table-empty">No vaccines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($vaccines as $vac): $cat = $vaccineCategoryMeta[$vac['category']] ?? ['icon' => 'bi-droplet-half', 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $medicineStatusMeta[$vac['status']] ?? $medicineStatusMeta['in_stock']; ?>
                <tr>
                    <td>
                        <div class="mvi-name-cell">
                            <span class="mvi-thumb" style="background:<?= htmlspecialchars($cat['bg']) ?>; color:<?= htmlspecialchars($cat['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
                            </span>
                            <span class="mvi-strong"><?= htmlspecialchars($vac['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($vac['category']) ?></td>
                    <td><?= htmlspecialchars($vac['unit']) ?></td>
                    <td class="<?= $vac['currentStock'] === 0 ? 'mvi-zero-stock' : '' ?>"><?= number_format($vac['currentStock']) ?></td>
                    <td><?= number_format($vac['minLevel']) ?></td>
                    <td><?= number_format($vac['reorderLevel']) ?></td>
                    <td class="<?= $vac['status'] === 'out_of_stock' ? 'mvi-expiry-warning' : '' ?>"><?= htmlspecialchars($vac['expiryDate']) ?></td>
                    <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    <td class="mvi-updated-cell"><?= htmlspecialchars($vac['lastUpdated']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $vaccinesTotal > 0 ? 'Showing ' . (($vaccinesCurrentPage - 1) * $vaccinesPerPage + 1) . ' to ' . min($vaccinesCurrentPage * $vaccinesPerPage, $vaccinesTotal) . ' of ' . $vaccinesTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination" aria-label="Vaccines pages">
            <?php if ($vaccinesCurrentPage > 1): ?>
            <a href="?<?= hc_mvi_vaccines_query($vaccinesCurrentPage - 1) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
            <?php foreach ($vaccinePaginationWindow as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="page-btn mvi-page-ellipsis">&hellip;</span>
                <?php else: ?>
                <a href="?<?= hc_mvi_vaccines_query($p) ?>" class="page-btn <?= $p === $vaccinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($vaccinesCurrentPage < $vaccinesTotalPages): ?>
            <a href="?<?= hc_mvi_vaccines_query($vaccinesCurrentPage + 1) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>
</div>
