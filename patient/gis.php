<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * GIS Mapping Page (patient/gis.php)
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data — $gisSummary/$topDiseases/
 * $healthTrend (includes/public/gis-data.php ->
 * includes/public/gis_data_firestore.php) and the map's per-barangay
 * case counts/colors (api/gis-data.php, fetched client-side by
 * assets/js/gis.js via Leaflet) all come from real
 * medical_records/consultations/patients data, reusing the same real
 * notifiable-disease keyword detection hc_disease_demand_forecast()
 * already provides elsewhere in this app.
 *
 * The date-range dropdown genuinely works — $_GET['date_range']
 * resolves to a range key ('1w'/'3m'/'6m'/'1y') in
 * includes/public/gis-data.php and is passed straight into
 * hc_gis_disease_overview(), so every real number on this page (and the
 * map's own separate api/gis-data.php fetch, kept in sync via
 * window.GIS_RANGE) recomputes over that real window — "Last Week" uses
 * real day-level buckets, not a month-sized one stretched over 7 days.
 * There's no separate disease filter anymore (removed along with the
 * old Apply Filters/Reset buttons, since neither worked without them).
 *
 * The map's polygon SHAPES themselves are still schematic, not real
 * surveyed barangay boundaries — see includes/shared/gis_geo.php's
 * header comment for why (no real GIS boundary data for Lipa City's
 * barangays exists in this project).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php';   // BASE_URL
require_once __DIR__ . '/../includes/public/data.php';       // $navItems (shared sidebar)
require_once __DIR__ . '/../includes/public/gis-data.php';   // $dateRangeFilterOptions, $selectedDateRange, $gisRange, $gisSummary, $topDiseases, $healthTrend, $seasonalOverview

$pageTitle   = 'GIS Mapping';
$pageCss     = 'gis.css';
$pageScripts = ['gis.js'];

// Leaflet + Chart.js (for the "Health Trend" line chart below), loaded
// only on this page (not site-wide). Same Chart.js version every other
// role's analytics pages already use.
$externalCss = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];
$externalJs  = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js'];

require_once __DIR__ . '/../includes/shared/header.php';
require_once __DIR__ . '/../includes/shared/sidebar.php';
?>

<div class="main-wrapper">
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <h1>GIS Mapping</h1>
        <p>View GIS mapping visualization to identify locations with high disease cases and healthcare trends within covered barangays.</p>
    </div>

    <!-- FILTER TOOLBAR -->
    <form class="gis-toolbar" id="gisFilterForm" action="gis.php" method="get">
        <div class="gis-search-field-wrap">
            <div class="gis-search-row">
                <div class="gis-search-field">
                    <i class="bi bi-search"></i>
                    <input type="text" id="gisSearchInput" name="location" placeholder="Search barangay or location" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-gis-search">Search</button>
            </div>
            <!-- Filled in by assets/js/gis.js only when a search finds no
                 reasonably close barangay match — empty/hidden otherwise. -->
            <div class="gis-search-feedback" id="gisSearchFeedback"></div>
        </div>

        <!-- Real — changing this reloads the page with ?date_range=...,
             which includes/public/gis-data.php resolves to a range key
             ('1w'/'3m'/'6m'/'1y') and re-runs the same real Firestore
             aggregation over (see that file's header comment).
             assets/js/gis.js submits the form on change (this <select>
             has no submit button of its own). -->
        <div class="filter-select-wrap has-icon">
            <i class="bi bi-calendar3 leading-icon"></i>
            <select class="filter-select" name="date_range" id="gisDateRangeSelect">
                <?php foreach ($dateRangeFilterOptions as $opt): ?>
                <option <?= $opt === $selectedDateRange ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <i class="bi bi-chevron-down chevron"></i>
        </div>
    </form>
    <script>
        window.GIS_RANGE = "<?= htmlspecialchars($gisRange, ENT_QUOTES) ?>";
        window.GIS_RANGE_LABEL = "<?= htmlspecialchars(strtolower($selectedDateRange), ENT_QUOTES) ?>";
    </script>

    <!-- TWO-COLUMN LAYOUT: ~30% INFO PANEL / ~70% MAP -->
    <div class="gis-layout">

        <!-- LEFT INFO PANEL -->
        <div class="gis-side">

            <!-- Health Trend: real monthly case-count line chart (was a
                 static Increasing/Decreasing/Stable legend — the
                 "Disease Cases by Barangay" 5-tier legend that used to
                 live in this card is still shown once, at the bottom of
                 the map card, so this doesn't just repeat it). Real data —
                 see includes/public/gis_data_firestore.php's header
                 comment. -->
            <div class="info-card">
                <div class="info-card-header">
                    <h2>Health Trend</h2>
                    <?php
                    $htDirLabel = ['up' => 'Increasing', 'down' => 'Decreasing', 'stable' => 'Stable'][$healthTrend['direction']];
                    $htDirIcon = ['up' => 'bi-graph-up-arrow', 'down' => 'bi-graph-down-arrow', 'stable' => 'bi-dash-lg'][$healthTrend['direction']];
                    ?>
                    <span class="health-trend-badge <?= htmlspecialchars($healthTrend['direction']) ?>">
                        <i class="bi <?= $htDirIcon ?>"></i> <?= $htDirLabel ?>
                    </span>
                </div>
                <div class="health-trend-canvas-box">
                    <canvas id="healthTrendChart"></canvas>
                </div>
                <p class="health-trend-caption">Real reported notifiable-disease cases, <?= htmlspecialchars(strtolower($selectedDateRange)) ?>.</p>
            </div>
            <script>window.GIS_HEALTH_TREND = <?= json_encode($healthTrend) ?>;</script>
            <script>window.GIS_FACILITIES = <?= json_encode($facilityMarkers) ?>;</script>

            <!-- Summary -->
            <div class="info-card">
                <div class="info-card-header">
                    <h2>Summary (<?= htmlspecialchars($selectedDateRange) ?>)</h2>
                </div>
                <div class="summary-grid">
                    <div class="summary-stat-box">
                        <div class="icon-circle red"><i class="bi bi-heart-pulse"></i></div>
                        <div>
                            <div class="stat-label">Total Cases</div>
                            <div class="stat-value-row">
                                <span class="stat-value"><?= htmlspecialchars($gisSummary['totalCases']) ?></span>
                                <span class="stat-trend <?= htmlspecialchars($gisSummary['totalTrendDir']) ?>">
                                    <i class="bi bi-arrow-<?= $gisSummary['totalTrendDir'] === 'down' ? 'down' : 'up' ?>"></i><?= htmlspecialchars($gisSummary['totalTrend']) ?>
                                </span>
                            </div>
                            <div class="stat-sub">vs previous <?= htmlspecialchars($gisSummary['periodLabel']) ?></div>
                        </div>
                    </div>
                    <div class="summary-stat-box">
                        <div class="icon-circle blue"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="stat-label">Affected Barangays</div>
                            <div class="stat-value-row">
                                <span class="stat-value"><?= htmlspecialchars($gisSummary['affectedCount']) ?> / <?= htmlspecialchars($gisSummary['affectedOfTotal']) ?></span>
                            </div>
                            <div class="stat-sub"><?= htmlspecialchars($gisSummary['affectedPercent']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top 5 Diseases -->
            <div class="info-card">
                <div class="info-card-header">
                    <h2>Top 5 Diseases</h2>
                    <!-- Real toggle (assets/js/gis.js) — swaps which real
                         number (case count vs. percent of total) each row
                         leads with; both are always real, this only
                         changes which one reads first. -->
                    <div class="mini-select-wrap">
                        <select class="mini-select" id="topDiseasesViewBy">
                            <option value="cases">View by: Cases</option>
                            <option value="percentage">View by: Percentage</option>
                        </select>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </div>
                <div class="disease-rank-list">
                    <?php if (empty($topDiseases)): ?>
                    <p class="text-muted small mb-0">No notifiable-disease cases reported <?= htmlspecialchars(strtolower($selectedDateRange)) ?>.</p>
                    <?php endif; ?>
                    <?php foreach ($topDiseases as $i => $d): ?>
                    <div class="disease-rank-row">
                        <span class="rank-badge"><?= $i + 1 ?></span>
                        <span class="disease-name"><?= htmlspecialchars($d['name']) ?></span>
                        <span class="disease-cases" data-cases="<?= htmlspecialchars($d['cases']) ?>" data-percent="<?= htmlspecialchars($d['percent']) ?>"><?= htmlspecialchars($d['cases']) ?></span>
                        <span class="disease-trend <?= htmlspecialchars($d['trend']) ?>">
                            <i class="bi bi-arrow-<?= $d['trend'] === 'up' ? 'up' : 'down' ?>"></i><?= htmlspecialchars($d['change']) ?>
                        </span>
                    </div>
                    <?php if (!empty($d['causes'])): ?>
                    <p class="disease-causes-note"><i class="bi bi-info-circle"></i> Likely contributing factors: <?= htmlspecialchars($d['causes']) ?></p>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Seasonal Patterns: true calendar-month/rainy-vs-dry view,
                 every real year on record merged — NOT the rolling
                 date-range window the rest of this page uses (see
                 includes/shared/predictive_analytics_core.php's
                 hc_gis_seasonal_overview() header comment). -->
            <div class="info-card">
                <div class="info-card-header">
                    <h2>Seasonal Patterns</h2>
                </div>
                <?php if (!$seasonalOverview['hasData']): ?>
                <p class="text-muted small mb-0">No notifiable-disease cases on record yet to find a seasonal pattern.</p>
                <?php else: ?>
                <p class="health-trend-caption" style="margin-top:0;">
                    Monthly case totals for <strong><?= htmlspecialchars($seasonalOverview['topCategory']) ?></strong>, every year on record.
                </p>
                <div class="health-trend-canvas-box">
                    <canvas id="seasonalChart"></canvas>
                </div>
                <div class="disease-rank-list" style="margin-top:12px;">
                    <?php foreach ($seasonalOverview['seasonCompare'] as $sc): ?>
                    <div class="disease-rank-row">
                        <span class="disease-name"><?= htmlspecialchars($sc['category']) ?></span>
                        <span class="disease-cases" title="Rainy season (Jun-Nov)"><i class="bi bi-cloud-rain-fill"></i> <?= (int) $sc['rainy'] ?></span>
                        <span class="disease-cases" title="Dry season (Dec-May)"><i class="bi bi-sun-fill"></i> <?= (int) $sc['dry'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <script>window.GIS_SEASONAL = <?= json_encode($seasonalOverview) ?>;</script>

            <!-- Hover/click hint -->
            <div class="med-notice" style="border-top:none; border-radius: 10px;">
                <i class="bi bi-info-circle-fill"></i>
                <span>Hover or click on a barangay to view more details.</span>
            </div>

        </div>
        <!-- /LEFT INFO PANEL -->

        <!-- RIGHT MAP PANEL -->
        <div class="gis-map-col">
            <div class="map-card">
                <div class="map-container">
                    <div id="gisMap"></div>

                    <button type="button" class="map-legend-btn" id="mapLegendToggle">Legend</button>
                    <div class="map-legend-panel" id="mapLegendPanel">
                        <div class="legend-row"><span class="dot" style="background:#D93025"></span> Very High (76&ndash;100)</div>
                        <div class="legend-row"><span class="dot" style="background:#E8710A"></span> High (51&ndash;75)</div>
                        <div class="legend-row"><span class="dot" style="background:#E8B800"></span> Moderate (26&ndash;50)</div>
                        <div class="legend-row"><span class="dot" style="background:#1E8E3E"></span> Low (1&ndash;25)</div>
                        <div class="legend-row"><span class="dot" style="background:#9CA3AF"></span> No Data</div>
                    </div>
                </div>

                <!-- Bottom full-width legend bar -->
                <div class="map-bottom-legend">
                    <div class="legend-group">
                        <div class="legend-group-title">Disease Intensity</div>
                        <div class="legend-group-items">
                            <span class="legend-item"><span class="dot" style="background:#D93025"></span> Very High <span class="range">(76 - 100)</span></span>
                            <span class="legend-item"><span class="dot" style="background:#E8710A"></span> High <span class="range">(51 - 75)</span></span>
                            <span class="legend-item"><span class="dot" style="background:#E8B800"></span> Moderate <span class="range">(26 - 50)</span></span>
                            <span class="legend-item"><span class="dot" style="background:#1E8E3E"></span> Low <span class="range">(1 - 25)</span></span>
                            <span class="legend-item"><span class="dot" style="background:#9CA3AF"></span> No Data</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /RIGHT MAP PANEL -->

    </div>

</main>
</div>

<?php require_once __DIR__ . '/../includes/shared/footer.php'; ?>
