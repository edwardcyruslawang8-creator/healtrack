<?php
/**
 * =====================================================================
 * Prescription Queue table + pagination partial
 * ---------------------------------------------------------------------
 * Shared verbatim between pharmacist/prescription_queue.php's initial
 * page load (wrapped in #rxqTable there) and
 * api/pharmacist-prescription-queue-status.php (polled by the inline
 * live-refresh script on that page via assets/js/queue-poll.js, so
 * this table updates itself once a doctor sends a new prescription or
 * another dispense happens elsewhere, without the pharmacist needing
 * to reload the page) — the SAME markup either way, so a live refresh
 * can never show something different than a real page load would
 * have. See includes/pharmacist/_prescription_queue_stats.php's header
 * comment for why the stat cards are a SEPARATE live-refresh zone
 * rather than bundled in here.
 *
 * Expects $orders, $statusMeta, $activeTab, $ordersTotal,
 * $ordersPerPage, $ordersCurrentPage, $ordersTotalPages,
 * $ordersPaginationWindow, and hc_rxq_query() already set/defined by
 * the includer (see
 * includes/pharmacist/prescription_queue_pharmacist_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table rxq-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Dosage / Instructions</th>
                    <th>Prescribed By</th>
                    <th>Date &amp; Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="adm-table-empty">No <?= htmlspecialchars($activeTab) ?> prescriptions match these filters.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($orders as $r): $status = $statusMeta[$r['status']] ?? $statusMeta['pending']; ?>
                <tr>
                    <td>
                        <div class="rxq-strong"><?= htmlspecialchars($r['patientName']) ?></div>
                    </td>
                    <td>
                        <span class="rxq-badge" style="background:<?= $r['type'] === 'vaccine' ? '#E8F0FE' : '#EFE7FB' ?>; color:<?= $r['type'] === 'vaccine' ? '#1A73E8' : '#7C3AED' ?>;"><?= htmlspecialchars($r['typeLabel']) ?></span>
                        <?php if ($r['isWalkIn']): ?>
                        <span class="rxq-badge" style="background:#FDF3DC; color:#B7791F; margin-left:4px;" title="Dispensed directly, no doctor's prescription">Walk-in</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['itemName']) ?></td>
                    <td>
                        <div><?= htmlspecialchars($r['dosage']) ?><?php if ($r['quantity'] !== null): ?> &bull; Qty: <?= (int) $r['quantity'] ?><?php endif; ?> &bull; <?= htmlspecialchars($r['frequency']) ?></div>
                        <?php if (!empty($r['instructions'])): ?>
                        <div class="rxq-patient-meta"><?= htmlspecialchars($r['instructions']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['prescribedBy']) ?></td>
                    <td class="rxq-datetime"><?= htmlspecialchars($r['date']) ?><span class="rxq-dot"></span><?= htmlspecialchars($r['time']) ?></td>
                    <td><span class="rxq-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    <td>
                        <div class="adm-action-btns">
                            <button type="button" class="rxq-btn-view"
                                data-patient-name="<?= htmlspecialchars($r['patientName']) ?>"
                                data-type-label="<?= htmlspecialchars($r['typeLabel']) ?>"
                                data-item-name="<?= htmlspecialchars($r['itemName']) ?>"
                                data-dosage="<?= htmlspecialchars($r['dosage']) ?>"
                                data-quantity="<?= $r['quantity'] !== null ? (int) $r['quantity'] : '' ?>"
                                data-frequency="<?= htmlspecialchars($r['frequency']) ?>"
                                data-duration="<?= htmlspecialchars($r['duration']) ?>"
                                data-route="<?= htmlspecialchars($r['route']) ?>"
                                data-instructions="<?= htmlspecialchars($r['instructions'] ?? '') ?>"
                                data-prescribed-by="<?= htmlspecialchars($r['prescribedBy']) ?>"
                                data-facility="<?= htmlspecialchars($r['facility']) ?>"
                                data-date="<?= htmlspecialchars($r['date']) ?>"
                                data-time="<?= htmlspecialchars($r['time']) ?>"
                                data-status-label="<?= htmlspecialchars($status['label']) ?>"
                                data-status-bg="<?= htmlspecialchars($status['bg']) ?>"
                                data-status-color="<?= htmlspecialchars($status['color']) ?>"
                                data-dispensed-date="<?= htmlspecialchars($r['dispensedDate']) ?>"
                                data-dispensed-time="<?= htmlspecialchars($r['dispensedTime']) ?>"
                                data-dispensed-by="<?= htmlspecialchars($r['dispensedBy']) ?>"
                                data-order-id="<?= htmlspecialchars($r['id']) ?>"
                                data-is-pending="<?= $r['status'] === 'pending' ? '1' : '0' ?>"
                                data-suggested-item-id="<?= htmlspecialchars($r['suggestedInventoryId']) ?>"
                                data-matched-item-name="<?= htmlspecialchars($r['matchedInventoryName'] ?? '') ?>"
                                data-dispensed-quantity="<?= htmlspecialchars((string) ($r['dispensedQuantity'] ?? '')) ?>"
                                data-dispensed-item-name="<?= htmlspecialchars($r['dispensedItemName'] ?? '') ?>">View Details</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $ordersTotal > 0 ? 'Showing ' . (($ordersCurrentPage - 1) * $ordersPerPage + 1) . ' to ' . min($ordersCurrentPage * $ordersPerPage, $ordersTotal) . ' of ' . $ordersTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination" aria-label="Queue pages">
            <?php // First/Previous are disabled (not links) on page 1, Next/Last on the last page — so a single page has no clickable arrows. ?>
            <?php if ($ordersCurrentPage > 1): ?>
            <a href="?<?= hc_rxq_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
            <a href="?<?= hc_rxq_query(['page' => $ordersCurrentPage - 1]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></span>
            <span class="page-btn disabled" aria-disabled="true" aria-label="Previous page"><i class="bi bi-chevron-left"></i></span>
            <?php endif; ?>
            <?php foreach ($ordersPaginationWindow as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php else: ?>
                <a href="?<?= hc_rxq_query(['page' => $p]) ?>" class="page-btn <?= $p === $ordersCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($ordersCurrentPage < $ordersTotalPages): ?>
            <a href="?<?= hc_rxq_query(['page' => $ordersCurrentPage + 1]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <a href="?<?= hc_rxq_query(['page' => $ordersTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            <?php else: ?>
            <span class="page-btn disabled" aria-disabled="true" aria-label="Next page"><i class="bi bi-chevron-right"></i></span>
            <span class="page-btn disabled" aria-disabled="true" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></span>
            <?php endif; ?>
        </nav>
    </div>
</div>
