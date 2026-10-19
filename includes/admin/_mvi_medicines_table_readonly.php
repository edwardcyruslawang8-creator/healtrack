<?php
/**
 * =====================================================================
 * Medicines Table + Pagination — Partial (Admin, read-only)
 * ---------------------------------------------------------------------
 * Same real $medicines data as includes/subadmin/_mvi_medicines_table.php,
 * minus the Actions column — a System Administrator has no write
 * access to this collection (api/update-medicine.php/api/delete-medicine.php
 * are pharmacist/subadmin-only), so no Edit/Delete/etc. buttons that
 * would just 403 are shown here.
 * =====================================================================
 */
?>
<div class="adm-card mvi-table-card">
    <div class="adm-table-wrap">
        <table class="adm-table mvi-table">
            <thead>
                <tr>
                    <th>Medicine Name</th>
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
                <?php if (empty($medicines)): ?>
                <tr>
                    <td colspan="9" class="adm-table-empty">No medicines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($medicines as $med): $cat = $medicineCategoryMeta[$med['category']] ?? ['icon' => 'bi-capsule', 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $medicineStatusMeta[$med['status']] ?? $medicineStatusMeta['in_stock']; $expiryBadge = $medicineExpiryBadgeMeta[$med['expiryStatus']] ?? null; ?>
                <tr>
                    <td>
                        <div class="mvi-name-cell">
                            <span class="mvi-thumb" style="background:<?= htmlspecialchars($cat['bg']) ?>; color:<?= htmlspecialchars($cat['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cat['icon']) ?>"></i>
                            </span>
                            <span class="mvi-strong"><?= htmlspecialchars($med['name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($med['category']) ?></td>
                    <td><?= htmlspecialchars($med['unit']) ?></td>
                    <td class="<?= $med['currentStock'] === 0 ? 'mvi-zero-stock' : '' ?>"><?= number_format($med['currentStock']) ?></td>
                    <td><?= number_format($med['minLevel']) ?></td>
                    <td><?= number_format($med['reorderLevel']) ?></td>
                    <td class="<?= $med['expiryStatus'] === 'expired' ? 'mvi-expiry-warning' : '' ?>">
                        <?= htmlspecialchars($med['expiryDate']) ?>
                        <?php if ($expiryBadge): ?>
                        <span class="mvi-badge mvi-expiry-badge" style="background:<?= htmlspecialchars($expiryBadge['bg']) ?>; color:<?= htmlspecialchars($expiryBadge['color']) ?>;"><?= htmlspecialchars($expiryBadge['label']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    <td class="mvi-updated-cell"><?= htmlspecialchars($med['lastUpdated']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $medicinesTotal > 0 ? 'Showing ' . (($medicinesCurrentPage - 1) * $medicinesPerPage + 1) . ' to ' . min($medicinesCurrentPage * $medicinesPerPage, $medicinesTotal) . ' of ' . $medicinesTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination" aria-label="Medicines pages">
            <?php if ($medicinesCurrentPage > 1): ?>
            <a href="?<?= hc_mvi_medicines_query($medicinesCurrentPage - 1) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
            <?php foreach ($medicinePaginationWindow as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="page-btn mvi-page-ellipsis">&hellip;</span>
                <?php else: ?>
                <a href="?<?= hc_mvi_medicines_query($p) ?>" class="page-btn <?= $p === $medicinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($medicinesCurrentPage < $medicinesTotalPages): ?>
            <a href="?<?= hc_mvi_medicines_query($medicinesCurrentPage + 1) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>
</div>
