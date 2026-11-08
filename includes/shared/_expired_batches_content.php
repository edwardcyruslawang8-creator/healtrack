<?php
/**
 * Expired Batches — page body (summary cards, filters, read-only table).
 * Shared by pharmacist/expired_batches.php and subadmin/expired_batches.php;
 * expects includes/shared/expired_batches_data.php to have run.
 * Monitoring only: no row actions, no unarchive.
 */
// Wording follows the type filter: vaccines are tracked by LOT, medicines
// by BATCH; the unfiltered (mixed) view names both.
$ebWord = ['vaccine' => 'Lot', 'medicine' => 'Batch'][$ebTypeFilter] ?? 'Batch / Lot';
$ebWordPlural = ['vaccine' => 'lots', 'medicine' => 'batches'][$ebTypeFilter] ?? 'batches/lots';
?>
    <!-- ============ SUMMARY CARDS ============ -->
    <div class="mvi-summary-row mvi-summary-row--3">
        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-calendar-x-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Expired Batches &amp; Lots Archived</span>
                <span class="mvi-summary-value"><?= number_format($ebSummary['totalBatches']) ?></span>
                <span class="mvi-summary-sub">Removed from inventory</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-capsule"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Items Affected</span>
                <span class="mvi-summary-value"><?= number_format($ebSummary['itemsAffected']) ?></span>
                <span class="mvi-summary-sub">Medicines &amp; vaccines with an expired batch or lot</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-calendar3"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Archived This Month</span>
                <span class="mvi-summary-value"><?= number_format($ebSummary['thisMonth']) ?></span>
                <span class="mvi-summary-sub"><?= htmlspecialchars(date('F Y')) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ FILTER & SEARCH ============ -->
    <form class="mvi-toolbar mvi-toolbar--search-first" method="get" action="expired_batches.php">
        <div class="mvi-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($ebSearchTerm) ?>" placeholder="Search <?= htmlspecialchars(strtolower($ebWord)) ?> number, item name, category..." autocomplete="off" enterkeyhint="search">
        </div>
        <select class="form-select mvi-filter-select" name="type" aria-label="Filter by type" onchange="this.form.submit()">
            <?php foreach ($ebTypeOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= $value === $ebTypeFilter ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- ============ EXPIRED BATCHES TABLE (read-only) ============ -->
    <div class="adm-card mvi-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table mvi-table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($ebWord) ?> Number</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Quantity Removed</th>
                        <th>Expiry Date</th>
                        <th>Archived On</th>
                        <th>Archived By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expiredBatches)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty"><?= $ebSummary['totalBatches'] === 0 ? 'No expired ' . $ebWordPlural . ' have been archived yet.' : 'No expired ' . $ebWordPlural . ' match your search.' ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($expiredBatches as $b): ?>
                    <tr>
                        <td class="mvi-strong"><?= htmlspecialchars($b['displayBatchNumber']) ?></td>
                        <td>
                            <span class="mvi-strong"><?= htmlspecialchars($b['itemName']) ?></span>
                            <?php if ($b['category'] !== ''): ?><span class="mvi-unit-sub"><?= htmlspecialchars($b['category']) ?></span><?php endif; ?>
                        </td>
                        <td><?= $b['itemType'] === 'vaccine' ? 'Vaccine' : 'Medicine' ?></td>
                        <td><?= htmlspecialchars(hc_qty_unit($b['quantity'], $b['unit'])) ?></td>
                        <td>
                            <?= htmlspecialchars($b['expiryDate']) ?>
                            <span class="mvi-badge mvi-expiry-badge" style="background:#FCE4E4; color:#D93025;">Expired</span>
                        </td>
                        <td class="mvi-updated-cell"><?= htmlspecialchars($b['archivedAt']) ?></td>
                        <td><?= htmlspecialchars($b['archivedBy']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $ebTotal > 0 ? 'Showing ' . (($ebCurrentPage - 1) * $ebPerPage + 1) . ' to ' . min($ebCurrentPage * $ebPerPage, $ebTotal) . ' of ' . $ebTotal . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Expired batches pages">
                <?php if ($ebCurrentPage > 1): ?>
                <a href="<?= htmlspecialchars(eb_page_url($ebCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $ebTotalPages; $p++): ?>
                <a href="<?= htmlspecialchars(eb_page_url($p)) ?>" class="page-btn <?= $p === $ebCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($ebCurrentPage < $ebTotalPages): ?>
                <a href="<?= htmlspecialchars(eb_page_url($ebCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <?php else: ?>
                <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
            </nav>
        </div>
    </div>
