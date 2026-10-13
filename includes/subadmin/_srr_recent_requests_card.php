<?php
/**
 * =====================================================================
 * Recent Stock Requests Card — Partial
 * ---------------------------------------------------------------------
 * Extracted out of subadmin/stock_request_reports.php so the exact same
 * markup can be rendered twice: once on a normal full page load, and
 * once as a small HTML fragment by api/stock-request-reports-recent-table.php
 * (assets/js/srr-recent-pagination.js's AJAX First/Previous/page-number/
 * Next/Last handler) — so paging through this one card never has to
 * reload the whole Stock Request Reports page (stat cards, both
 * doughnuts, and the Request Fulfillment Summary table next to it all
 * stay exactly as they were), and the two render paths can never drift
 * out of sync with each other.
 *
 * Expects $recentRequests, $requestStatusMeta, $recentRequestsTotal,
 * $recentRequestsCurrentPage, and $recentRequestsTotalPages to already
 * be set (by includes/subadmin/stock_request_reports_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card" id="srrRecentRequestsCard">
    <div class="adm-card-header">
        <h2>Recent Stock Requests</h2>
        <a href="stock_requests.php" class="adm-card-footer-link" style="margin-top:0;">View All</a>
    </div>
    <div class="adm-table-wrap">
        <table class="adm-table srr-compact-table" style="min-width:0;" data-sortable="true">
            <thead>
                <tr>
                    <th>Requested By</th>
                    <th>Category</th>
                    <th>Items Requested</th>
                    <th>Qty</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentRequests)): ?>
                <tr data-no-sort>
                    <td colspan="6" class="adm-table-empty">No stock requests for this period.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($recentRequests as $r): $status = $requestStatusMeta[$r['status']] ?? ['label' => ucfirst($r['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                <tr>
                    <td><?= htmlspecialchars($r['requestedBy']) ?></td>
                    <td><?= htmlspecialchars($r['category']) ?></td>
                    <td><?= htmlspecialchars($r['items']) ?></td>
                    <td><?= number_format($r['qty']) ?></td>
                    <td><?= htmlspecialchars($r['dateRequested']) ?></td>
                    <td><span class="arp-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text">
            <?php if ($recentRequestsTotal > 0): $shownFrom = ($recentRequestsCurrentPage - 1) * 5 + 1; $shownTo = $shownFrom + count($recentRequests) - 1; ?>
            Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($recentRequestsTotal) ?> entries
            <?php else: ?>
            No entries to show
            <?php endif; ?>
        </span>
        <?= hc_render_pagination_nav('Recent stock requests pages', 'srrPage', $recentRequestsCurrentPage, $recentRequestsTotalPages) ?>
    </div>
</div>
