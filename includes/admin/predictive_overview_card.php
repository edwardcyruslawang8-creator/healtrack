<?php
/**
 * =====================================================================
 * Predictive & Prescriptive Analytics — Preview Card (partial)
 * ---------------------------------------------------------------------
 * Renders $predictiveOverviewCards / $predictiveOverviewTrend /
 * $predictiveOverviewTopAlerts as one .adm-card — shared by
 * admin/admin_dashboard.php (fed by
 * includes/admin/predictive_overview_firestore.php's system-wide
 * numbers), admin/facility_analytics.php's Overview tab (fed by that
 * SAME facility's own pt1/pt2/pt3 — see
 * includes/admin/facility_analytics_firestore.php's own comment right
 * above where these variables are built), subadmin/dashboard.php and
 * medical-staff/dashboard.php + analytics_reports.php (each fed by
 * their own facility- or personally-scoped
 * includes/{role}/predictive_overview_firestore.php) — the markup is
 * identical everywhere, only which data file populated the variables
 * differs.
 *
 * $predictiveOverviewTrend (optional — omit the chart entirely by
 * leaving it unset/empty) is the one real trend/forecast CHART behind
 * these "Prediction card" tiles, not just the tiles' own single
 * numbers, per this app's original Predictive & Prescriptive Analytics
 * spec (6.3.1–6.3.4: Prediction cards, Forecast charts, Trend graphs,
 * Risk/alert indicators, Recommendation cards — the full breakdown, all
 * 5 of those, only lives on the page/tab this card's own link points
 * to; this preview keeps the 3 lightest-weight ones: prediction cards,
 * one forecast trend chart, and risk alerts). Always hc_disease_demand_forecast()'s
 * own 'forecastTrend' shape ({labels, actual, forecast}) — solid
 * "Actual" line up to today, dashed "Forecast" line for the next 7
 * days. Used by every role whose card includes 6.3.1 (admin/subadmin/
 * medical-staff/medical_technician/radiologic_technologist — the
 * latter two forecast their own lab/x-ray referral volume instead of
 * consultations, with $predictiveOverviewTrendLabel overridden to
 * match). Pharmacist's own card omits this chart entirely (no
 * 6.3.1-shaped trend is this role's job) — it used to show a
 * medicine/vaccine "all at-risk items" multi-line chart instead, since
 * removed (mostly drew flat, uninformative lines once an item could be
 * flagged at-risk with no real order history — see
 * includes/pharmacist/predictive_overview_firestore.php's own comment).
 * Rendered with a small self-contained inline <script> (Chart.js is
 * already loaded on every page that includes this partial) rather than
 * a shared JS file, since multiple pages with different existing JS
 * files include this same partial.
 *
 * Include with these set beforehand ($predictiveOverviewLinkHref/Label
 * are optional — omit the link entirely by leaving
 * $predictiveOverviewLinkHref unset/null):
 *   $predictiveOverviewLinkHref  — footer link target, e.g.
 *                                  "facility_management.php", or an
 *                                  in-page Bootstrap tab id like
 *                                  "#tab-predictive" (admin/facility_analytics.php's
 *                                  Overview tab uses this — the link
 *                                  gets data-bs-toggle="tab" instead of
 *                                  being a plain anchor jump, so it
 *                                  actually switches to that tab pane
 *                                  rather than scrolling to a hidden one).
 *   $predictiveOverviewLinkLabel — footer link text
 * =====================================================================
 */
$predictiveOverviewLinkHref = $predictiveOverviewLinkHref ?? null;
$predictiveOverviewLinkLabel = $predictiveOverviewLinkLabel ?? 'View Facility Analytics';
$predictiveOverviewLinkIsTab = $predictiveOverviewLinkHref !== null && str_starts_with($predictiveOverviewLinkHref, '#');
$predictiveOverviewTrend = $predictiveOverviewTrend ?? null;
?>
<style>
/* Self-contained (not assets/css/predictive_analytics.css — most pages
   that include this partial don't load that stylesheet) so the
   "Unusual" anomaly badge and "Likely contributing factors" note on
   $predictiveOverviewTopAlerts render correctly no matter which host
   page included this card. Only the two rules this partial actually
   uses; the full severity-pill palette lives in predictive_analytics.css
   for the dedicated Predictive Analytics page itself. */
.adm-predictive-overview-card .pn-severity.unusual{
  display: inline-flex;
  align-items: center;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .3px;
  text-transform: uppercase;
  padding: 3px 9px;
  border-radius: 50px;
  white-space: nowrap;
  background: #F1E8FD;
  color: #8B5CF6;
  margin-left: 6px;
}
.adm-predictive-overview-card .pn-causes-note{
  display: block;
  font-size: 11px;
  color: var(--adm-text-muted, #6B7280);
  margin-top: 2px;
  line-height: 1.4;
}
</style>
<div class="adm-card adm-predictive-overview-card">
    <div class="adm-card-header">
        <h2><i class="bi bi-graph-up-arrow me-2"></i>Predictive &amp; Prescriptive Analytics</h2>
        <?php if ($predictiveOverviewLinkHref !== null): ?>
        <a href="<?= htmlspecialchars($predictiveOverviewLinkHref) ?>" class="adm-card-footer-link" style="margin-top:0;" <?= $predictiveOverviewLinkIsTab ? 'data-bs-toggle="tab" data-bs-target="' . htmlspecialchars($predictiveOverviewLinkHref) . '"' : '' ?>><?= htmlspecialchars($predictiveOverviewLinkLabel) ?> <i class="bi bi-arrow-right"></i></a>
        <?php endif; ?>
    </div>

    <div class="adm-mini-stats-row">
        <?php foreach ($predictiveOverviewCards as $c): ?>
        <div class="adm-mini-stat">
            <div class="adm-mini-stat-icon" style="background:<?= htmlspecialchars($c['iconBg']) ?>; color:<?= htmlspecialchars($c['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($c['icon']) ?>"></i>
            </div>
            <div>
                <div class="adm-mini-stat-label"><?= htmlspecialchars($c['label']) ?></div>
                <div class="adm-mini-stat-value"><?= htmlspecialchars($c['value']) ?></div>
                <div class="adm-mini-stat-sub"><?= htmlspecialchars($c['sub']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    // Callers whose actual/forecast trend isn't about consultations (e.g.
    // medical_technician's lab/x-ray volume forecast) set
    // $predictiveOverviewTrendLabel themselves beforehand to override this
    // default — see includes/medical_technician/predictive_overview_firestore.php.
    $predictiveOverviewTrendLabel = $predictiveOverviewTrendLabel ?? 'Consultation Forecast Trend (actual vs. next 7 days)';
    ?>
    <?php if (!empty($predictiveOverviewTrend['labels'])): ?>
    <div class="adm-mini-trend-wrap">
        <div class="adm-mini-trend-label"><?= htmlspecialchars($predictiveOverviewTrendLabel) ?></div>
        <div class="adm-mini-trend-canvas-box">
            <canvas id="predictiveOverviewTrendChart"></canvas>
        </div>
    </div>
    <script>
    /* This card is included mid-<main>, well before the Chart.js
       <script src> tag near the bottom of the page (same convention
       every page here follows — vendor scripts load last). Running
       immediately (as this used to) meant `Chart` genuinely wasn't
       defined yet at this point in the parse, so the guard below always
       returned early and silently drew nothing — the chart never
       appeared in the browser, even though the canvas/markup were
       correctly present in the page's HTML (which is all a curl-based
       render check like `grep`ping the response actually verifies —
       it can't catch a script that runs too early). Deferring to
       DOMContentLoaded guarantees every earlier synchronous <script>,
       Chart.js included, has already run. */
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        var canvas = document.getElementById('predictiveOverviewTrendChart');
        if (!canvas || canvas.dataset.rendered) return;
        canvas.dataset.rendered = '1';

        var trend = <?= json_encode($predictiveOverviewTrend) ?>;
        var rootStyle = getComputedStyle(document.documentElement);
        var gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
        var tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        label: 'Actual', data: trend.actual,
                        borderColor: '#1A73E8', backgroundColor: 'rgba(26, 115, 232, 0.10)',
                        borderWidth: 2, fill: true, tension: 0.35, pointRadius: 2,
                    },
                    {
                        label: 'Forecast', data: trend.forecast,
                        borderColor: '#D93025', borderDash: [6, 4],
                        borderWidth: 2, fill: false, tension: 0.35, pointRadius: 2, spanGaps: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxTicksLimit: 7 } },
                },
            },
        });
    });
    </script>
    <?php endif; ?>

    <?php if (!empty($predictiveOverviewTopAlerts)): ?>
    <div class="adm-status-list">
        <?php foreach ($predictiveOverviewTopAlerts as $r): ?>
        <div class="adm-status-row">
            <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($r['icon']) ?>"></i>
            </span>
            <span class="adm-status-body">
                <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?><?php if (!empty($r['anomaly'])): ?><span class="pn-severity unusual" title="More than 2 standard deviations above this pair's own 30-day baseline">Unusual</span><?php endif; ?></span>
                <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                <?php if (!empty($r['causes'])): ?><span class="pn-causes-note">Likely contributing factors: <?= htmlspecialchars($r['causes']) ?></span><?php endif; ?>
            </span>
            <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted small mb-0">No active risk alerts right now.</p>
    <?php endif; ?>
</div>
