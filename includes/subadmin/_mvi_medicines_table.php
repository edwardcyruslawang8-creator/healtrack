<?php
/**
 * =====================================================================
 * Medicines Table + Pagination — Partial
 * ---------------------------------------------------------------------
 * Extracted out of subadmin/medicine_vaccine_inventory.php so the exact
 * same markup can be rendered twice: once on a normal full page load,
 * and once as a small HTML fragment by api/get-medicine-inventory-table.php
 * (assets/js/mvi-ajax-pagination.js's AJAX Previous/page-number/Next
 * handler) — so paging never has to duplicate this table's markup, and
 * the two can never drift out of sync with each other.
 *
 * Expects $medicines, $medicineCategoryMeta, and $medicineStatusMeta to
 * already be set (by includes/subadmin/medicine_vaccine_inventory_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card mvi-table-card" id="mvi-medicines-table-card">
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($medicines)): ?>
                <tr>
                    <td colspan="10" class="adm-table-empty">No medicines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($medicines as $med): $cat = $medicineCategoryMeta[$med['category']] ?? ['icon' => 'bi-capsule', 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $medicineStatusMeta[$med['status']] ?? $medicineStatusMeta['in_stock']; ?>
                <tr
                    data-med-id="<?= htmlspecialchars($med['id']) ?>"
                    data-med-type="medicine"
                    data-med-name="<?= htmlspecialchars($med['name']) ?>"
                    data-med-category="<?= htmlspecialchars($med['category']) ?>"
                    data-med-form="<?= htmlspecialchars($med['form']) ?>"
                    data-med-unit="<?= htmlspecialchars($med['unit']) ?>"
                    data-med-expiry="<?= htmlspecialchars($med['expiryDateRaw']) ?>"
                    data-med-expiry-display="<?= htmlspecialchars($med['expiryDate']) ?>"
                    data-med-stock="<?= (int) $med['currentStock'] ?>"
                    data-med-min-level="<?= (int) $med['minLevel'] ?>"
                    data-med-reorder-level="<?= (int) $med['reorderLevel'] ?>"
                    data-med-desc="<?= htmlspecialchars($med['desc']) ?>"
                    data-med-status-label="<?= htmlspecialchars($status['label']) ?>"
                >
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
                    <td class="<?= $med['status'] === 'out_of_stock' ? 'mvi-expiry-warning' : '' ?>"><?= htmlspecialchars($med['expiryDate']) ?></td>
                    <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    <td class="mvi-updated-cell"><?= htmlspecialchars($med['lastUpdated']) ?></td>
                    <td>
                        <div class="adm-action-btns">
                            <button type="button" class="mvi-manage-btn edit">Manage Medicine</button>
                            <div class="dropdown">
                                <button type="button" class="adm-action-btn mvi-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item mvi-view-details" href="#"><i class="bi bi-eye me-2"></i>View Details</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-clock-history me-2"></i>View Stock History</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-archive me-2"></i>Archive</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger mvi-delete" href="#"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $medicinesTotal > 0 ? 'Showing ' . (($medicinesCurrentPage - 1) * $medicinesPerPage + 1) . ' to ' . min($medicinesCurrentPage * $medicinesPerPage, $medicinesTotal) . ' of ' . $medicinesTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination mvi-ajax-pagination" aria-label="Medicines pages" data-mvi-tab="medicines">
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
