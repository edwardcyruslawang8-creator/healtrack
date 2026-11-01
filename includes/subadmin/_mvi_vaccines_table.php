<?php
/**
 * =====================================================================
 * Vaccines Table + Pagination — Partial
 * ---------------------------------------------------------------------
 * Same idea as includes/subadmin/_mvi_medicines_table.php — see that
 * file's own header comment (same simplified-overview redesign,
 * "Manage Medicine"/Archive only).
 * =====================================================================
 */
?>
<div class="adm-card mvi-table-card" id="mvi-vaccines-table-card">
    <div class="adm-table-wrap">
        <table class="adm-table mvi-table">
            <thead>
                <tr>
                    <th>Vaccine Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vaccines)): ?>
                <tr>
                    <td colspan="5" class="adm-table-empty">No vaccines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($vaccines as $vac): $cat = $vaccineCategoryMeta[$vac['category']] ?? ['icon' => 'bi-droplet-half', 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                <tr data-med-id="<?= htmlspecialchars($vac['id']) ?>" data-med-name="<?= htmlspecialchars($vac['name']) ?>">
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
                    <td class="mvi-updated-cell"><?= htmlspecialchars($vac['lastUpdated']) ?></td>
                    <td>
                        <div class="adm-action-btns">
                            <a href="manage_medicine.php?id=<?= urlencode($vac['id']) ?>&amp;type=vaccine" class="mvi-manage-btn" aria-label="Manage this vaccine">Manage Vaccine</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $vaccinesTotal > 0 ? 'Showing ' . (($vaccinesCurrentPage - 1) * $vaccinesPerPage + 1) . ' to ' . min($vaccinesCurrentPage * $vaccinesPerPage, $vaccinesTotal) . ' of ' . $vaccinesTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination mvi-ajax-pagination" aria-label="Vaccines pages" data-mvi-tab="vaccines">
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
