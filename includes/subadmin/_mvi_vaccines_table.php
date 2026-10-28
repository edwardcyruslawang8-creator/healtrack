<?php
/**
 * =====================================================================
 * Vaccines Table + Pagination — Partial
 * ---------------------------------------------------------------------
 * Same idea as includes/subadmin/_mvi_medicines_table.php — see that
 * file's own header comment.
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
                <?php if (empty($vaccines)): ?>
                <tr>
                    <td colspan="10" class="adm-table-empty">No vaccines recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($vaccines as $vac): $cat = $vaccineCategoryMeta[$vac['category']] ?? ['icon' => 'bi-droplet-half', 'bg' => '#EEF0F3', 'color' => '#6B7280']; $status = $medicineStatusMeta[$vac['status']] ?? $medicineStatusMeta['in_stock']; $expiryBadge = $medicineExpiryBadgeMeta[$vac['expiryStatus']] ?? null; ?>
                <tr
                    data-med-id="<?= htmlspecialchars($vac['id']) ?>"
                    data-med-type="vaccine"
                    data-med-name="<?= htmlspecialchars($vac['name']) ?>"
                    data-med-category="<?= htmlspecialchars($vac['category']) ?>"
                    data-med-form="<?= htmlspecialchars($vac['form']) ?>"
                    data-med-unit="<?= htmlspecialchars($vac['unit']) ?>"
                    data-med-expiry="<?= htmlspecialchars($vac['expiryDateRaw']) ?>"
                    data-med-expiry-display="<?= htmlspecialchars($vac['expiryDate']) ?>"
                    data-med-stock="<?= (int) $vac['currentStock'] ?>"
                    data-med-min-level="<?= (int) $vac['minLevel'] ?>"
                    data-med-reorder-level="<?= (int) $vac['reorderLevel'] ?>"
                    data-med-max-level="<?= (int) $vac['maxLevel'] ?>"
                    data-med-desc="<?= htmlspecialchars($vac['desc']) ?>"
                    data-med-status-label="<?= htmlspecialchars($status['label']) ?>"
                >
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
                    <td class="<?= $vac['expiryStatus'] === 'expired' ? 'mvi-expiry-warning' : '' ?>">
                        <?= htmlspecialchars($vac['expiryDate']) ?>
                        <?php if ($expiryBadge): ?>
                        <span class="mvi-badge mvi-expiry-badge" style="background:<?= htmlspecialchars($expiryBadge['bg']) ?>; color:<?= htmlspecialchars($expiryBadge['color']) ?>;"><?= htmlspecialchars($expiryBadge['label']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    <td class="mvi-updated-cell"><?= htmlspecialchars($vac['lastUpdated']) ?></td>
                    <td>
                        <div class="adm-action-btns">
                            <button type="button" class="mvi-manage-btn edit">Manage Vaccine</button>
                            <div class="dropdown">
                                <button type="button" class="adm-action-btn mvi-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item mvi-view-details" href="#"><i class="bi bi-eye me-2"></i>View Details</a></li>
                                    <li><a class="dropdown-item mvi-stock-history" href="#"><i class="bi bi-clock-history me-2"></i>View Stock History</a></li>
                                    <li><a class="dropdown-item mvi-archive" href="#"><i class="bi bi-archive me-2"></i>Archive</a></li>
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
