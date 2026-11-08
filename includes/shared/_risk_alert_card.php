<?php
/**
 * Risk & Alert Indicators card (includes/shared/_risk_alert_card.php)
 * ---------------------------------------------------------------------
 * One card per Predictive & Prescriptive tab on admin/facility_analytics.php
 * and subadmin/predictive_analytics.php. Rows are the ACTIVE alerts from
 * hc_risk_dashboard_lists() (includes/shared/risk_alerts.php) — created
 * and resolved automatically, never dismissed by hand. The View History
 * button opens the page's one shared #riskHistoryModal
 * (includes/shared/risk_alert_history_modal.php).
 *
 * Expects: $riskCardRows (array of row shapes from hc_risk_dashboard_lists()),
 * $riskCardGroup (this tab's hc_risk_types() group — the history modal
 * opens scoped to it).
 */
$riskCardRows = $riskCardRows ?? [];
$riskCardGroup = $riskCardGroup ?? '';
?>
<div class="adm-card pn-risk-card">
    <div class="adm-card-header pn-risk-head">
        <h3>Risk &amp; Alert Indicators</h3>
        <button type="button" class="pn-history-btn" data-bs-toggle="modal" data-bs-target="#riskHistoryModal" data-risk-group="<?= htmlspecialchars($riskCardGroup) ?>">
            <i class="bi bi-clock-history" aria-hidden="true"></i><span>View History</span>
        </button>
    </div>
    <?php if ($riskCardRows === []): ?>
    <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No active risk indicators. All monitored conditions are currently within normal thresholds.</p></div>
    <?php else: ?>
    <div class="adm-status-list">
        <?php foreach ($riskCardRows as $r): ?>
        <div class="adm-status-row">
            <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
            <span class="adm-status-body">
                <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span><?php if (!empty($r['isForecast'])): ?><span class="pn-severity forecast" title="Based on projected (not yet recorded) data">Forecast</span><?php endif; ?><?php if (!empty($r['anomaly'])): ?><span class="pn-severity unusual" title="More than 2 standard deviations above this pair's own 30-day baseline">Unusual</span><?php endif; ?></span>
                <span class="adm-status-desc"><?php if (($r['typeLabel'] ?? '') !== ''): ?><strong><?= htmlspecialchars($r['typeLabel']) ?>:</strong> <?php endif; ?><?= htmlspecialchars($r['desc']) ?></span>
                <?php if (!empty($r['causes'])): ?><span class="pn-causes-note">Likely contributing factors: <?= htmlspecialchars($r['causes']) ?></span><?php endif; ?>
                <?php if (!empty($r['resolvingNote'])): ?><span class="pn-resolving-note"><i class="bi bi-arrow-down-right-circle"></i> <?= htmlspecialchars($r['resolvingNote']) ?></span><?php endif; ?>
            </span>
            <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
