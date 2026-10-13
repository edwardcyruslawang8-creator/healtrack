<?php
/**
 * =====================================================================
 * All User Accounts — table + pagination partial
 * ---------------------------------------------------------------------
 * Extracted out of admin/account_monitoring.php so the exact same
 * markup is reused by api/get-account-rows-table.php (the AJAX
 * endpoint assets/js/account-monitoring-ajax.js fetches whenever the
 * search box/Role filter/Status filter/pagination changes) — this way
 * a filter/page change only ever re-renders this table + its footer,
 * never the whole page (stat cards, charts, sidebar, topbar all stay
 * put). Expects $accountRowsPage, $allAccountRows, $accountRowsTotal,
 * $accountRowsPerPage, $accountRowsCurrentPage, $accountRowsTotalPages,
 * $accountRowsPaginationWindow, and hc_acct_query() — all set by
 * includes/admin/account_monitoring_firestore.php.
 * =====================================================================
 */
?>
<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Account</th>
                <th>Email</th>
                <th>Role</th>
                <th>Facility</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($accountRowsPage)): ?>
            <tr>
                <td colspan="5" class="adm-table-empty"><?= empty($allAccountRows) ? 'No accounts yet.' : 'No accounts match your search.' ?></td>
            </tr>
            <?php endif; ?>
            <?php foreach ($accountRowsPage as $r): ?>
            <tr>
                <td>
                    <div class="adm-activity-name-cell">
                        <span class="adm-avatar-sm initials <?= htmlspecialchars($r['avatarColor']) ?>"><?= htmlspecialchars($r['initials']) ?></span>
                        <span><?= htmlspecialchars($r['name']) ?></span>
                    </div>
                </td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td><?= htmlspecialchars($r['roleLabel']) ?></td>
                <td><?= $r['facility'] !== '' ? htmlspecialchars($r['facility']) : '—' ?></td>
                <td><span class="acm-account-status-badge <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="adm-table-footer">
    <span class="adm-showing-text"><?= $accountRowsTotal > 0 ? 'Showing ' . (($accountRowsCurrentPage - 1) * $accountRowsPerPage + 1) . ' to ' . min($accountRowsCurrentPage * $accountRowsPerPage, $accountRowsTotal) . ' of ' . $accountRowsTotal . ' accounts' : 'No accounts to show' ?></span>
    <nav class="adm-pagination" aria-label="Account list pages">
        <?php if ($accountRowsCurrentPage > 1): ?>
        <a href="?<?= hc_acct_query(['acct_page' => $accountRowsCurrentPage - 1]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
        <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-left"></i></span>
        <?php endif; ?>
        <?php foreach ($accountRowsPaginationWindow as $p): ?>
            <?php if ($p === '...'): ?>
            <span class="page-ellipsis">&hellip;</span>
            <?php else: ?>
            <a href="?<?= hc_acct_query(['acct_page' => $p]) ?>" class="page-btn <?= $p === $accountRowsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($accountRowsCurrentPage < $accountRowsTotalPages): ?>
        <a href="?<?= hc_acct_query(['acct_page' => $accountRowsCurrentPage + 1]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
        <?php else: ?>
        <span class="page-btn disabled" aria-disabled="true"><i class="bi bi-chevron-right"></i></span>
        <?php endif; ?>
    </nav>
</div>
