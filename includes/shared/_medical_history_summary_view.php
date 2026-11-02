<?php
/**
 * =====================================================================
 * Medical History Summary — view (partial)
 * ---------------------------------------------------------------------
 * Filter bar, 4 summary cards, the per-category charts and the
 * by-barangay breakdowns, rendered from
 * includes/shared/medical_history_summary.php's result. Shared by
 * subadmin/medical_history_reports.php and admin/facility_analytics.php's
 * Medical History tab, so both roles see the exact same report.
 * Charts are drawn by assets/js/medical_history_reports.js from the
 * inline window.MEDICAL_HISTORY_DATA below. Styles: "mhr-" rules in
 * assets/css/analytics_reports.css (plus the shared arp-/sad- atoms).
 *
 * Include with these set beforehand:
 *   $mh, $mhFilters, $mhFilterOptions, $mhStatCards
 *   $mhExportReport — report key for the Print/PDF/Excel actions
 *                     (includes/shared/report_export_actions.php), or ''
 *                     to render none
 * Requires includes/shared/query_helpers.php (hc_url_with_params()).
 * =====================================================================
 */

$mhCats = $mh['categories'];
$mhVisible = array_flip($mh['visible']);
$mhChartRows = fn (array $items) => max(1, min(10, count($items)));
?>
<div class="arp-filter-bar mhr-filter-bar">
    <?php $drfState = $mhFilters['date']; $drfBtnClass = 'arp-filter-select'; include __DIR__ . '/date_range_filter_dropdown.php'; ?>

    <?php $sfdOptions = $mhFilterOptions['barangay']; $sfdParam = 'barangay'; $sfdCurrent = $mhFilters['barangay']; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/simple_filter_dropdown.php'; ?>

    <?php $sfdOptions = $mhFilterOptions['ageGroup']; $sfdParam = 'ageGroup'; $sfdCurrent = $mhFilters['ageGroup']; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/simple_filter_dropdown.php'; ?>

    <?php $sfdOptions = $mhFilterOptions['gender']; $sfdParam = 'gender'; $sfdCurrent = $mhFilters['gender']; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/simple_filter_dropdown.php'; ?>

    <div class="dropdown">
        <button class="btn btn-light border arp-filter-select dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-journal-medical"></i> <?= htmlspecialchars($mhFilterOptions['category'][$mhFilters['category']]) ?>
        </button>
        <ul class="dropdown-menu">
            <?php foreach ($mhFilterOptions['category'] as $val => $label): ?>
            <li><a class="dropdown-item <?= $mhFilters['category'] === $val ? 'active' : '' ?>" href="<?= htmlspecialchars(hc_url_with_params(['historyCategory' => $val])) ?>"><?= htmlspecialchars($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <span class="arp-filter-spacer"></span>

    <?php if ($mhExportReport !== '') { $reaReport = $mhExportReport; include __DIR__ . '/report_export_actions.php'; } ?>
</div>

<div class="arp-kpi-row mhr-kpi-row">
    <?php foreach ($mhStatCards as $stat): ?>
    <div class="adm-card arp-kpi-card">
        <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
            <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
        </div>
        <div class="arp-kpi-text">
            <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
            <span class="arp-kpi-value <?= !empty($stat['text']) ? 'mhr-kpi-value-text' : '' ?>" title="<?= htmlspecialchars($stat['value']) ?>"><?= htmlspecialchars($stat['value']) ?></span>
            <span class="arp-kpi-trend"><?= htmlspecialchars($stat['sub']) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($mh['scopePatients'] === 0): ?>
<div class="adm-card mhr-empty-card">
    <i class="bi bi-person-x"></i>
    <p>No patients match the selected filters, so there is no medical history to summarize.</p>
</div>
<?php else: ?>

<div class="mhr-grid-2">
    <?php foreach (['conditions', 'allergies', 'surgeries', 'family'] as $key): if (!isset($mhVisible[$key])) continue; $cat = $mhCats[$key]; ?>
    <div class="adm-card sad-chart-card">
        <div class="sad-card-header">
            <h2><?= htmlspecialchars($key === 'family' ? 'Family Medical History Patterns' : 'Most Common ' . $cat['label']) ?></h2>
            <span class="mhr-card-note"><?= number_format($cat['patients']) ?> patient<?= $cat['patients'] === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($cat['items'] === []): ?>
        <div class="mhr-empty">
            <i class="bi bi-inbox"></i>
            <span>No <?= htmlspecialchars(mb_strtolower($cat['label'])) ?> recorded for the patients in this view<?= $key !== 'family' && $mhFilters['date']['option'] !== 'all' ? ' within the selected date range' : '' ?>.</span>
        </div>
        <?php else: ?>
        <div class="mhr-axis-label">Unique patients<?= count($cat['items']) > 10 ? ' (top 10 of ' . count($cat['items']) . ')' : '' ?></div>
        <div class="mhr-bar-box" style="height:<?= 34 * $mhChartRows($cat['items']) + 30 ?>px;">
            <canvas id="mhChart-<?= $key ?>" aria-label="<?= htmlspecialchars($cat['label']) ?> by number of patients" role="img"></canvas>
        </div>
        <?php if ($key === 'family'): ?>
        <div class="mhr-relations">
            <?php foreach (array_slice($cat['items'], 0, 5) as $item): ?>
            <div class="mhr-relation-row">
                <span class="mhr-relation-label"><?= htmlspecialchars($item['label']) ?></span>
                <span class="mhr-relation-value"><?= htmlspecialchars(implode(', ', array_map(fn ($r, $n) => $r . ' (' . $n . ')', array_keys($item['relations']), $item['relations']))) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php foreach (['conditions' => 'Medical Conditions by Barangay', 'allergies' => 'Allergies by Barangay'] as $key => $title): if (!isset($mhVisible[$key])) continue; $cat = $mhCats[$key]; ?>
<div class="adm-card mhr-barangay-card">
    <div class="sad-card-header">
        <h2><?= htmlspecialchars($title) ?></h2>
        <span class="mhr-card-note">Top 8 barangays &middot; top 5 <?= $key === 'conditions' ? 'conditions' : 'allergies' ?></span>
    </div>
    <?php if ($cat['barangays'] === []): ?>
    <div class="mhr-empty">
        <i class="bi bi-inbox"></i>
        <span>No <?= $key === 'conditions' ? 'chronic conditions' : 'allergies' ?> recorded for the patients in this view.</span>
    </div>
    <?php else: ?>
    <div class="mhr-barangay-grid">
        <div>
            <div class="mhr-bar-box" style="height:<?= 34 * min(8, count($cat['barangays'])) + 70 ?>px;">
                <canvas id="mhBarangayChart-<?= $key ?>" aria-label="<?= htmlspecialchars($title) ?>" role="img"></canvas>
            </div>
            <p class="mhr-footnote">A patient with more than one <?= $key === 'conditions' ? 'condition' : 'allergy' ?> is counted under each of them.</p>
        </div>
        <div class="adm-table-wrap mhr-table-wrap">
            <table class="adm-table mhr-table" style="min-width:0;" data-sortable="true">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Patients</th>
                        <th>% of Barangay</th>
                        <th>Most Common</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cat['barangays'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['barangay']) ?></td>
                        <td><?= number_format($row['patients']) ?></td>
                        <td><?= rtrim(rtrim(number_format($row['pct'], 1), '0'), '.') ?>%</td>
                        <td><?= htmlspecialchars($row['top']) ?> <span class="mhr-muted">(<?= number_format($row['topPatients']) ?>)</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>

<div class="arp-footer-notice">
    <i class="bi bi-info-circle-fill"></i>
    <span>Counts are unique patients from recorded medical histories. Percentages are of the <?= number_format($mh['scopePatients']) ?> patient<?= $mh['scopePatients'] === 1 ? '' : 's' ?> matching the filters. The date range applies to each record's date; family history records have no date.</span>
</div>

<script id="reportDataScript">
    // Read by assets/js/medical_history_reports.js; the id lets
    // assets/js/ajax-report-nav.js re-run it after a filter swap.
    window.MEDICAL_HISTORY_DATA = <?= json_encode([
        'charts' => array_map(fn ($cat) => [
            'labels' => array_column(array_slice($cat['items'], 0, 10), 'label'),
            'values' => array_column(array_slice($cat['items'], 0, 10), 'patients'),
            'pcts' => array_column(array_slice($cat['items'], 0, 10), 'pct'),
        ], $mhCats),
        'barangay' => array_map(fn ($cat) => [
            'labels' => array_column(array_slice($cat['barangays'], 0, 8), 'barangay'),
            'series' => array_map(fn ($label, $i) => [
                'label' => $label,
                'values' => array_map(fn ($row) => $row['series'][$i] ?? 0, array_slice($cat['barangays'], 0, 8)),
            ], $cat['seriesLabels'], array_keys($cat['seriesLabels'])),
        ], ['conditions' => $mhCats['conditions'], 'allergies' => $mhCats['allergies']]),
    ]) ?>;
</script>
