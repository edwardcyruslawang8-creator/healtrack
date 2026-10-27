<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * GIS Mapping (admin/gis_mapping.php)
 * ---------------------------------------------------------------------
 * Reuses the exact same sidebar/topbar shell as the rest of admin/*.php
 * (assets/css/admin-dashboard.css, "adm-" prefixed classes). The GIS
 * content itself (search/date-range toolbar, Health Trend chart,
 * Summary/Top 5 Diseases cards, and the Leaflet map) is the SAME real
 * markup/CSS/JS patient/gis.php already uses (assets/css/gis.css,
 * assets/js/gis.js) — see includes/admin/gis_mapping_firestore.php's
 * header comment for why the underlying data is guaranteed identical
 * to what the public homepage preview and the full patient-facing map
 * show, not a separate copy.
 *
 * window.APP_BASE_URL is set explicitly below since assets/js/gis.js
 * expects it (normally set by includes/shared/header.php, which the
 * admin shell doesn't load) — without it, its fetch() calls to
 * api/gis-data.php would resolve against the wrong base path.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php';          // t()

// Only a signed-in admin (see api/session-login.php) may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/gis_mapping_firestore.php';
// $adminProfile, $dateRangeFilterOptions, $selectedDateRange, $gisRange,
// $gisSummary, $topDiseases, $healthTrend

// Sidebar nav — GIS Mapping is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php', 'active' => true],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-bell-fill',           'label' => t('nav.notifications'),       'href' => 'notifications.php', 'badge' => hc_admin_unread_notification_count()],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Admin', 'href' => 'audit_logs.php?role=admin'],
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'Doctor', 'href' => 'audit_logs.php?role=doctor'],
            ['label' => 'Medical Staff', 'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => 'Medical Technologist', 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => 'Radiologic Technologist', 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => 'Pharmacist', 'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-gear-fill',           'label' => t('nav.profile_settings'),    'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GIS Mapping — HEALTrack</title>
<script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Leaflet (map) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<!-- Shared admin shell (sidebar, topbar, cards, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Real GIS Mapping page styles — same file patient/gis.php uses -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/gis.css') ?>">
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR
     ============================================================= -->
<aside class="adm-sidebar" id="admSidebar">

    <!-- Brand -->
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p><?= t('app.tagline') ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Admin profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#admNavAuditLogs" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="admNavAuditLogs">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="admNavAuditLogs">
                <ul class="adm-nav-submenu">
                    <?php foreach ($item['children'] as $child): ?>
                    <li><a href="<?= htmlspecialchars($child['href']) ?>" class="adm-nav-sublink <?= !empty($child['active']) ? 'active' : '' ?>"><?= htmlspecialchars($child['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php else: ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="adm-nav-badge" id="ntfSidebarBadge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="adm-nav-bottom">
        <hr class="adm-sidebar-divider" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/api/logout.php" class="adm-nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span><?= t('nav.logout') ?></span>
        </a>
    </div>
</aside>

<!-- =============================================================
     MAIN CONTENT
     ============================================================= -->
<main class="adm-main">

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= t('page.gis_mapping.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.gis_mapping.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= date('F j, Y') ?></span></span>
                    <span class="adm-dt-sep">|</span>
                    <span><i class="bi bi-clock"></i><span class="adm-dt-time"><?= date('g:i A') ?></span></span>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_admin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_admin_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i><?= t('nav.profile_settings') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('nav.logout') ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ FILTER TOOLBAR (real — same as patient/gis.php) ============ -->
    <form class="gis-toolbar" id="gisFilterForm" action="gis_mapping.php" method="get">
        <div class="gis-search-field-wrap">
            <div class="gis-search-row">
                <div class="gis-search-field">
                    <i class="bi bi-search"></i>
                    <input type="text" id="gisSearchInput" name="location" placeholder="Search barangay or location" value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-gis-search">Search</button>
            </div>
            <div class="gis-search-feedback" id="gisSearchFeedback"></div>
        </div>

        <!-- A real dropdown menu (not a native <select>) — its own
             opened option list follows this page's actual design instead
             of the browser's unstyled native popup; assets/js/gis.js
             null-checks #gisDateRangeSelect so removing it here is safe
             (that element only ever existed on this page anyway). -->
        <div class="dropdown gis-daterange-dropdown">
            <button type="button" class="gis-daterange-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-calendar3"></i> <?= htmlspecialchars($selectedDateRange) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($dateRangeFilterOptions as $opt): ?>
                <li><a class="dropdown-item <?= $opt === $selectedDateRange ? 'active' : '' ?>" href="?<?= hc_gis_admin_query(['date_range' => $opt]) ?>"><?= htmlspecialchars($opt) ?></a></li>
                <?php endforeach; ?>
            </ul>
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

            <!-- Health Trend: real line chart — see includes/public/gis_data_firestore.php -->
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
                    <?php endforeach; ?>
                </div>
            </div>

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

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/gis.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
</body>
</html>
