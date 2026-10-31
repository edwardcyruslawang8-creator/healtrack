<?php
/**
 * =====================================================================
 * Analytics & Reports — two-row tab navigation (partial)
 * ---------------------------------------------------------------------
 * Shared by every Analytics & Reports page (analytics_reports.php and
 * its report-tab siblings). Renders the SAME $analyticsTabs list each
 * page's own -firestore.php already builds (hrefs, labels, 'active'
 * flag untouched), just split into two rows by 'key':
 *   - Row 1: the main sections (Overview, Predictive & Prescriptive).
 *   - Row 2: every report category, in its existing order.
 * Every link keeps data-ajax-tab, so assets/js/ajax-tab-nav.js's
 * smooth <main> swap (which re-renders this whole nav, active state
 * included) works exactly as before. Styled by .arp-nav in
 * assets/css/analytics_reports.css.
 *
 * Include with $analyticsTabs set beforehand.
 * =====================================================================
 */

$arpPrimaryTabKeys = ['overview', 'predictive_analytics'];
$arpPrimaryTabs = array_values(array_filter($analyticsTabs, fn ($tab) => in_array($tab['key'], $arpPrimaryTabKeys, true)));
$arpReportTabs = array_values(array_filter($analyticsTabs, fn ($tab) => !in_array($tab['key'], $arpPrimaryTabKeys, true)));
?>
<nav class="arp-nav" aria-label="Analytics &amp; Reports sections">
    <div class="arp-nav-row">
        <span class="arp-nav-label">Analytics &amp; Reports</span>
        <div class="arp-nav-primary">
            <?php foreach ($arpPrimaryTabs as $tab): ?>
            <a href="<?= htmlspecialchars($tab['href']) ?>" class="arp-nav-tab arp-nav-tab-primary <?= !empty($tab['active']) ? 'active' : '' ?>" <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?> data-ajax-tab><?= htmlspecialchars($tab['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="arp-nav-row arp-nav-row-reports">
        <span class="arp-nav-label">Report Categories</span>
        <div class="arp-nav-reports">
            <?php foreach ($arpReportTabs as $tab): ?>
            <a href="<?= htmlspecialchars($tab['href']) ?>" class="arp-nav-tab <?= !empty($tab['active']) ? 'active' : '' ?>" <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?> data-ajax-tab><?= htmlspecialchars($tab['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
