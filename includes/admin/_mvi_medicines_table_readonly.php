<?php
/**
 * =====================================================================
 * Medicines Table + Pagination — Partial (Admin, read-only)
 * ---------------------------------------------------------------------
 * Same simplified-overview redesign includes/subadmin/_mvi_medicines_table.php
 * got — Current Stock/Minimum Level/Reorder Level/Expiry Date/Status/
 * Batch Number columns are all gone (per request), leaving Medicine
 * Name/Category/Unit/Last Updated. Actions holds ONE real link now —
 * "Manage Medicine", to admin/manage_medicine.php's own read-only
 * detail page (the real Batch Inventory table, no Edit/Delete/Restock/
 * Archive/Dispense trigger anywhere on it either) — a System
 * Administrator still has no write access to this collection
 * (api/update-medicine.php/api/delete-medicine.php are pharmacist/
 * subadmin-only), so nothing that would just 403 is shown here.
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
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                <tr>
                    <td colspan="5" class="adm-table-empty">No medicines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($medicines as $med): $cat = $medicineCategoryMeta[$med['category']] ?? ['icon' => 'bi-capsule', 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
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
                    <td class="mvi-updated-cell"><?= htmlspecialchars($med['lastUpdated']) ?></td>
                    <td>
                        <div class="adm-action-btns">
                            <a href="manage_medicine.php?id=<?= urlencode($med['id']) ?>&amp;type=medicine" class="mvi-manage-btn" aria-label="View this medicine">Manage Medicine</a>
                        </div>
                    </td>
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
