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
 * A simplified overview, same redesign pharmacist/medicine_vaccine_inventory.php
 * already got — Current Stock/Minimum Level/Reorder Level/Expiry Date/
 * Status/Batch Number columns are all gone (per request), leaving
 * Medicine Name/Category/Unit/Last Updated. "Manage Medicine" is the
 * one real action left on this table now — a real link to
 * subadmin/manage_medicine.php's own detail page (not a modal
 * trigger). Edit/Restock/Archive all live there instead; the three-dot
 * dropdown that used to hold Archive/Delete/View Details/View Stock
 * History is gone from this table entirely, no row-level Archive
 * button here anymore either.
 *
 * Expects $medicines to already be set (by
 * includes/subadmin/medicine_vaccine_inventory_firestore.php).
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
                <tr data-med-id="<?= htmlspecialchars($med['id']) ?>" data-med-name="<?= htmlspecialchars($med['name']) ?>">
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
                            <a href="manage_medicine.php?id=<?= urlencode($med['id']) ?>&amp;type=medicine" class="mvi-manage-btn" aria-label="Manage this medicine">Manage Medicine</a>
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
