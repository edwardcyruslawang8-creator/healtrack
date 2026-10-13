<?php
/**
 * =====================================================================
 * Top 5 Common Health Conditions — list + axis partial
 * ---------------------------------------------------------------------
 * Extracted out of subadmin/dashboard.php so the exact same markup is
 * reused by api/subadmin-dashboard-card.php (the AJAX endpoint the
 * card's own "All Time/Today/This Week/This Month" filter now calls,
 * instead of reloading the whole page) — same pattern already used by
 * includes/admin/_account_rows_table.php for Account Monitoring's
 * table. Expects $topHealthConditions, $topHealthConditionsAxis,
 * $topHealthConditionsAxisMax (all set by
 * includes/subadmin/subadmin_dashboard_firestore.php).
 * =====================================================================
 */
?>
<div class="sad-condition-list">
    <?php if (empty($topHealthConditions)): ?>
    <p class="adm-table-empty">No health conditions on record for this range.</p>
    <?php endif; ?>
    <?php foreach ($topHealthConditions as $c): ?>
    <div class="sad-condition-row">
        <span class="sad-condition-label"><?= htmlspecialchars($c['label']) ?></span>
        <div class="sad-condition-bar-track">
            <div class="sad-condition-bar-fill" style="width:<?= round($c['value'] / $topHealthConditionsAxisMax * 100) ?>%;"></div>
        </div>
        <span class="sad-condition-value"><?= (int) $c['value'] ?></span>
    </div>
    <?php endforeach; ?>
</div>
<div class="sad-condition-axis">
    <?php foreach ($topHealthConditionsAxis as $tick): ?>
    <span><?= number_format($tick) ?></span>
    <?php endforeach; ?>
</div>
