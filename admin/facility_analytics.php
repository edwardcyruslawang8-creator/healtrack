<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Facility Analytics & Reports (admin/facility_analytics.php)
 * ---------------------------------------------------------------------
 * Reached via the "View Analytics" button on facility_management.php,
 * which links here as facility_analytics.php?facility_id=<id>. Reuses
 * the same sidebar/topbar shell as the other admin pages
 * (assets/css/admin-dashboard.css) plus its own styles for the facility
 * header card, tabs, summary cards, and inventory sections
 * (assets/css/facility_analytics.css, "an-" prefixed).
 *
 * CONNECTED — every number on every tab is computed live from
 * Firestore in includes/admin/facility_analytics_firestore.php, scoped
 * to whichever facility_id was requested (see that file's header
 * comment for what's genuinely facility-scoped vs. system-wide, and
 * for the whole tab strip: Overview, Predictive & Prescriptive,
 * Patients, Records, Services, Staff, Medicines, Vaccines, Reports).
 *
 * "Predictive & Prescriptive" forecasts this SAME facility's own
 * historical trend (linear regression over its real daily patient/
 * consultation counts — see the data file's header comment) and turns
 * that + its current real numbers into rule-based recommendations —
 * genuinely different per facility, since it's built from that
 * facility's own real activity, not a shared template.
 *
 * Chart data is handed to assets/js/facility_analytics.js via
 * window.FACILITY_ANALYTICS_DATA the same way admin_dashboard.php
 * hands off its doughnut chart data.
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

require_once __DIR__ . '/../includes/admin/facility_analytics_firestore.php';
// $adminProfile, $facility, $facilityName, $summaryCards, $patientTrend, $demographics, $demographicsTotal,
// $topServices, $topServicesMax, $medicineInventory, $topLowStockMedicines, $lowStockMedicinesMax,
// $medicineStockDistribution, $vaccineInventory, $vaccineStock, $vaccineStockMax,
// $vaccineStockDistribution, $inventorySummary, $analyticsTabs,
// $patientFit, $consultFit, $patientForecastTotal, $consultForecastTotal, $patientPast7Total,
// $consultPast7Total, $projectedMonthlyPatients, $projectedMonthlyGrowthPct, $newPatientsLastMonth,
// $forecastTrend, $recommendations

// Sidebar nav — Facility Management stays active (this page is reached from it).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php', 'active' => true],
    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'User',      'href' => 'audit_logs.php?role=user'],
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
<title>Facility Analytics & Reports — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Facility Analytics page styles (header card, tabs, summary cards, inventory) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/facility_analytics.css') ?>">
<!-- Predictive Analytics styles (the 4 additional 6.3.1–6.3.4 categories on the Predictive tab below) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/predictive_analytics.css') ?>">
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

    <!-- ============ BACK LINK ============ -->
    <a href="facility_management.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> <?= t('back.facilities') ?>
    </a>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= t('page.facility_analytics.title') ?></h1>
            <p class="adm-page-subtitle"><?= t('page.facility_analytics.subtitle') ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= date('F j, Y') ?></span></span>
                    <span class="adm-dt-sep">|</span>
                    <span><i class="bi bi-clock"></i><span class="adm-dt-time"><?= date('g:i A') ?></span></span>
                </div>

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

    <?php if ($facilitiesLoadFailed): ?>
    <div class="alert alert-danger py-2 px-3 small" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Couldn't load facility data right now — Firestore didn't respond (this is usually a temporary
        outage or the daily quota being reached, not a missing facility). Please try again in a moment.
    </div>
    <?php endif; ?>

    <!-- ============ FACILITY INFO CARD ============ -->
    <div class="an-facility-card">
        <div class="an-facility-icon"><i class="bi bi-building"></i></div>
        <div class="an-facility-main">
            <div class="an-facility-name-row">
                <h2><?= htmlspecialchars($facility['name']) ?></h2>
                <span class="an-status-badge"><?= htmlspecialchars($facility['status']) ?></span>
            </div>
            <div class="an-facility-location"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($facility['location']) ?></div>
        </div>

        <div class="an-facility-metrics">
            <div class="an-facility-metric">
                <span class="an-metric-label">Total Patients</span>
                <span class="an-metric-value"><?= number_format((int) $facility['patients']) ?></span>
                <span class="an-metric-sub">This month <i class="bi bi-arrow-up-short"></i><?= (int) $facility['patientsGrowth'] ?>%</span>
            </div>
            <div class="an-facility-metric">
                <span class="an-metric-label">Total Records</span>
                <span class="an-metric-value"><?= number_format((int) $facility['records']) ?></span>
                <span class="an-metric-sub">This month <i class="bi bi-arrow-up-short"></i><?= (int) $facility['recordsGrowth'] ?>%</span>
            </div>
            <div class="an-facility-metric">
                <span class="an-metric-label">Total Staff</span>
                <span class="an-metric-value"><?= number_format((int) $facility['staff']) ?></span>
                <span class="an-metric-sub">This month <i class="bi bi-arrow-right-short"></i><?= (int) $facility['staffGrowth'] ?>%</span>
            </div>
        </div>
    </div>

    <!-- ============ ANALYTICS TABS ============ -->
    <ul class="nav adm-tabs" role="tablist">
        <?php foreach ($analyticsTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>"
                    id="tab-<?= htmlspecialchars($tab['key']) ?>-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-<?= htmlspecialchars($tab['key']) ?>"
                    type="button" role="tab">
                <i class="bi <?= htmlspecialchars($tab['icon']) ?>"></i> <?= htmlspecialchars($tab['label']) ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content an-tab-content">
        <?php foreach ($analyticsTabs as $tab): ?>
        <div class="tab-pane fade <?= !empty($tab['active']) ? 'show active' : '' ?>" id="tab-<?= htmlspecialchars($tab['key']) ?>" role="tabpanel">
        <?php if ($tab['key'] === 'overview'): ?>

            <!-- ============ SUMMARY CARDS (with sparklines) ============ -->
            <div class="an-summary-row">
                <?php foreach ($summaryCards as $card): ?>
                <div class="an-summary-card">
                    <div class="an-summary-top">
                        <div class="an-summary-icon" style="background:<?= htmlspecialchars($card['iconBg']) ?>; color:<?= htmlspecialchars($card['iconColor']) ?>;">
                            <i class="bi <?= htmlspecialchars($card['icon']) ?>"></i>
                        </div>
                        <div>
                            <div class="an-summary-label"><?= htmlspecialchars($card['label']) ?></div>
                            <div class="an-summary-value"><?= number_format((int) $card['value']) ?></div>
                            <div class="an-summary-sub"><?= htmlspecialchars($card['sub']) ?> <span class="an-summary-growth"><i class="bi bi-arrow-up-short"></i><?= (int) $card['growth'] ?>%</span></div>
                        </div>
                    </div>
                    <div class="an-sparkline-box">
                        <canvas class="an-sparkline" id="spark-<?= htmlspecialchars($card['key']) ?>"></canvas>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
                 This facility's own real numbers, reusing pt1-pt4 (this
                 same page's own "Predictive & Prescriptive" tab
                 computation) rather than a second query — see
                 includes/admin/facility_analytics_firestore.php's own
                 comment right above where $predictiveOverviewCards is
                 built. The link jumps to this page's own #tab-predictive
                 tab, not a different page. -->
            <?php
            $predictiveOverviewLinkHref = '#tab-predictive';
            $predictiveOverviewLinkLabel = 'View Full Predictive & Prescriptive Analytics';
            require __DIR__ . '/../includes/admin/predictive_overview_card.php';
            ?>

            <!-- ============ PATIENT TREND + DEMOGRAPHICS + TOP SERVICES ============ -->
            <div class="an-charts-row">

                <div class="an-card an-trend-card" id="patientTrendCard" data-facility-id="<?= htmlspecialchars($facility['id']) ?>">
                    <div class="an-card-header">
                        <h3>Patient Trend</h3>
                        <div class="dropdown">
                            <button class="an-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($patientTrendRangeOptions[$patientTrendRange]) ?></button>
                            <!-- Real query param ($_GET['range'], includes/admin/facility_analytics_firestore.php).
                                 assets/js/facility_analytics.js intercepts the click and fetches
                                 api/get-patient-trend.php to update just this card's chart — the
                                 href stays a real full-reload URL too (?range=...), so the range still
                                 works with JS disabled or on a fetch failure. -->
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php foreach ($patientTrendRangeOptions as $rangeKey => $rangeLabel): ?>
                                <li><a class="dropdown-item pt-range-link <?= $rangeKey === $patientTrendRange ? 'active' : '' ?>" data-range="<?= htmlspecialchars($rangeKey) ?>" href="?facility_id=<?= urlencode($facility['id']) ?>&range=<?= urlencode($rangeKey) ?>"><?= htmlspecialchars($rangeLabel) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="an-trend-legend">
                        <span><span class="dot" style="background:#1A73E8;"></span> New Patients</span>
                        <span><span class="dot" style="background:#1E8E3E;"></span> Returning Patients</span>
                    </div>
                    <div class="an-trend-canvas-box">
                        <canvas id="patientTrendChart"></canvas>
                    </div>
                </div>

                <div class="an-card an-demographics-card">
                    <div class="an-card-header"><h3>Patient Demographics</h3></div>
                    <div class="an-donut-wrap">
                        <div class="an-donut-canvas-box">
                            <canvas id="demographicsChart"></canvas>
                            <div class="an-donut-center">
                                <div class="an-donut-total"><?= number_format($demographicsTotal) ?></div>
                                <div class="an-donut-total-label">Total</div>
                            </div>
                        </div>
                    </div>
                    <div class="an-donut-legend">
                        <?php foreach ($demographics as $d): ?>
                        <div class="legend-row">
                            <span class="dot" style="background:<?= htmlspecialchars($d['color']) ?>;"></span>
                            <span class="legend-label"><?= htmlspecialchars($d['label']) ?></span>
                            <span class="legend-value"><?= (int) $d['value'] ?> (<?= rtrim(rtrim(number_format($d['pct'], 1), '0'), '.') ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="an-card an-services-card">
                    <div class="an-card-header">
                        <h3>Top Services</h3>
                        <span class="an-card-header-note">Consultations</span>
                    </div>
                    <div class="an-services-list">
                        <?php foreach ($topServices as $s): ?>
                        <div class="an-service-row">
                            <span class="an-service-icon" style="background:<?= htmlspecialchars($s['iconBg']) ?>; color:<?= htmlspecialchars($s['iconColor']) ?>;">
                                <i class="bi <?= htmlspecialchars($s['icon']) ?>"></i>
                            </span>
                            <div class="an-service-body">
                                <div class="an-service-top">
                                    <span class="an-service-label"><?= htmlspecialchars($s['label']) ?></span>
                                    <span class="an-service-value"><?= (int) $s['value'] ?></span>
                                </div>
                                <div class="an-service-bar-track">
                                    <div class="an-service-bar-fill" style="width:<?= round($s['value'] / $topServicesMax * 100) ?>%; background:<?= htmlspecialchars($s['color']) ?>;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ============ MEDICINE / VACCINE INVENTORY + SUMMARY ============ -->
            <div class="an-inventory-row">

                <!-- Medicine Inventory -->
                <div class="an-card an-inventory-card" id="medicineInventoryCard">
                    <div class="an-card-header">
                        <h3>Medicine Inventory</h3>
                        <div class="dropdown">
                            <button class="an-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($medCategoryFilter) ?></button>
                            <!-- Real query param (?medCategory=) — assets/js/facility_analytics.js intercepts
                                 the click and fetches api/get-inventory-card.php to refresh just this card
                                 (pills, list, donut); the href stays a real full-reload URL too. -->
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item inv-category-link <?= $medCategoryFilter === 'All' ? 'active' : '' ?>" data-category="All" href="?facility_id=<?= urlencode($facility['id']) ?>&medCategory=All">All</a></li>
                                <?php foreach ($medicineCategories as $cat): ?>
                                <li><a class="dropdown-item inv-category-link <?= $medCategoryFilter === $cat ? 'active' : '' ?>" data-category="<?= htmlspecialchars($cat) ?>" href="?facility_id=<?= urlencode($facility['id']) ?>&medCategory=<?= urlencode($cat) ?>"><?= htmlspecialchars($cat) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="an-inv-pills">
                        <div class="an-inv-pill">
                            <span class="an-inv-pill-label">Total Medicines</span>
                            <span class="an-inv-pill-value" data-pill="total"><?= number_format($medicineInventoryCard['total']) ?></span>
                        </div>
                        <div class="an-inv-pill good">
                            <span class="an-inv-pill-label">In Stock</span>
                            <span class="an-inv-pill-value" data-pill="inStock"><?= number_format($medicineInventoryCard['inStock']) ?></span>
                        </div>
                        <div class="an-inv-pill warn">
                            <span class="an-inv-pill-label">Low Stock</span>
                            <span class="an-inv-pill-value" data-pill="lowStock"><?= number_format($medicineInventoryCard['lowStock']) ?></span>
                        </div>
                        <div class="an-inv-pill bad">
                            <span class="an-inv-pill-label">Out of Stock</span>
                            <span class="an-inv-pill-value" data-pill="outOfStock"><?= number_format($medicineInventoryCard['outOfStock']) ?></span>
                        </div>
                    </div>

                    <div class="an-inv-split">
                        <div class="an-inv-list-col">
                            <h4 data-list-label>Top Low Stock Medicines</h4>
                            <div class="an-inv-list-header"><span>Stock Left</span></div>
                            <div data-list-rows>
                            <?php if ($topLowStockMedicinesCard === []): ?>
                            <p class="adm-table-empty" style="padding:10px 0;">No items in this category.</p>
                            <?php endif; ?>
                            <?php foreach ($topLowStockMedicinesCard as $m): ?>
                            <div class="an-inv-row">
                                <span class="an-inv-row-name"><?= htmlspecialchars($m['name']) ?></span>
                                <div class="an-inv-row-bar-track">
                                    <div class="an-inv-row-bar-fill" style="width:<?= round($m['stock'] / $lowStockMedicinesMaxCard * 100) ?>%;"></div>
                                </div>
                                <span class="an-inv-row-value"><?= (int) $m['stock'] ?></span>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="an-inv-donut-col">
                            <h4>Stock Status Distribution</h4>
                            <div class="an-donut-canvas-box small"><canvas id="medicineStockChart"></canvas></div>
                            <div class="an-donut-legend compact" data-legend>
                                <?php foreach ($medicineStockDistributionCard as $d): ?>
                                <div class="legend-row">
                                    <span class="dot" style="background:<?= htmlspecialchars($d['color']) ?>;"></span>
                                    <span class="legend-label"><?= htmlspecialchars($d['label']) ?></span>
                                    <span class="legend-value"><?= rtrim(rtrim(number_format($d['pct'], 1), '0'), '.') ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vaccine Inventory -->
                <div class="an-card an-inventory-card" id="vaccineInventoryCard">
                    <div class="an-card-header">
                        <h3>Vaccine Inventory</h3>
                        <div class="dropdown">
                            <button class="an-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($vacCategoryFilter) ?></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item inv-category-link <?= $vacCategoryFilter === 'All' ? 'active' : '' ?>" data-category="All" href="?facility_id=<?= urlencode($facility['id']) ?>&vacCategory=All">All</a></li>
                                <?php foreach ($vaccineCategories as $cat): ?>
                                <li><a class="dropdown-item inv-category-link <?= $vacCategoryFilter === $cat ? 'active' : '' ?>" data-category="<?= htmlspecialchars($cat) ?>" href="?facility_id=<?= urlencode($facility['id']) ?>&vacCategory=<?= urlencode($cat) ?>"><?= htmlspecialchars($cat) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="an-inv-pills">
                        <div class="an-inv-pill">
                            <span class="an-inv-pill-label">Total Vaccines</span>
                            <span class="an-inv-pill-value" data-pill="total"><?= number_format($vaccineInventoryCard['total']) ?></span>
                        </div>
                        <div class="an-inv-pill good">
                            <span class="an-inv-pill-label">In Stock</span>
                            <span class="an-inv-pill-value" data-pill="inStock"><?= number_format($vaccineInventoryCard['inStock']) ?></span>
                        </div>
                        <div class="an-inv-pill warn">
                            <span class="an-inv-pill-label">Low Stock</span>
                            <span class="an-inv-pill-value" data-pill="lowStock"><?= number_format($vaccineInventoryCard['lowStock']) ?></span>
                        </div>
                        <div class="an-inv-pill bad">
                            <span class="an-inv-pill-label">Out of Stock</span>
                            <span class="an-inv-pill-value" data-pill="outOfStock"><?= number_format($vaccineInventoryCard['outOfStock']) ?></span>
                        </div>
                    </div>

                    <div class="an-inv-split">
                        <div class="an-inv-list-col">
                            <h4 data-list-label>Vaccine Stock Overview</h4>
                            <div class="an-inv-list-header"><span>Stock Left</span></div>
                            <div data-list-rows>
                            <?php if ($vaccineStockCard === []): ?>
                            <p class="adm-table-empty" style="padding:10px 0;">No items in this category.</p>
                            <?php endif; ?>
                            <?php foreach ($vaccineStockCard as $v): ?>
                            <div class="an-inv-row">
                                <span class="an-inv-row-name"><?= htmlspecialchars($v['name']) ?></span>
                                <div class="an-inv-row-bar-track">
                                    <div class="an-inv-row-bar-fill" style="width:<?= round($v['stock'] / $vaccineStockMaxCard * 100) ?>%;"></div>
                                </div>
                                <span class="an-inv-row-value"><?= (int) $v['stock'] ?></span>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="an-inv-donut-col">
                            <h4>Vaccine Status Distribution</h4>
                            <div class="an-donut-canvas-box small"><canvas id="vaccineStockChart"></canvas></div>
                            <div class="an-donut-legend compact" data-legend>
                                <?php foreach ($vaccineStockDistributionCard as $d): ?>
                                <div class="legend-row">
                                    <span class="dot" style="background:<?= htmlspecialchars($d['color']) ?>;"></span>
                                    <span class="legend-label"><?= htmlspecialchars($d['label']) ?></span>
                                    <span class="legend-value"><?= rtrim(rtrim(number_format($d['pct'], 1), '0'), '.') ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Summary -->
                <div class="an-card an-summary-panel">
                    <div class="an-card-header"><h3>Inventory Summary</h3></div>

                    <div class="an-summary-tiles">
                        <div class="an-summary-tile">
                            <span class="an-tile-label">Total Items</span>
                            <span class="an-tile-value"><?= number_format($inventorySummary['totalItems']) ?></span>
                        </div>
                        <div class="an-summary-tile good">
                            <span class="an-tile-label">In Stock</span>
                            <span class="an-tile-value"><?= number_format($inventorySummary['inStock']) ?></span>
                        </div>
                        <div class="an-summary-tile warn">
                            <span class="an-tile-label">Low Stock</span>
                            <span class="an-tile-value"><?= number_format($inventorySummary['lowStock']) ?></span>
                        </div>
                        <div class="an-summary-tile bad">
                            <span class="an-tile-label">Out of Stock</span>
                            <span class="an-tile-value"><?= number_format($inventorySummary['outOfStock']) ?></span>
                        </div>
                    </div>

                    <div class="an-summary-highlight">
                        <div class="an-highlight-icon warn"><i class="bi bi-calendar-event-fill"></i></div>
                        <div>
                            <div class="an-highlight-value"><?= (int) $inventorySummary['expiringSoonCount'] ?></div>
                            <div class="an-highlight-label">Items expiring in <?= (int) $inventorySummary['expiringSoonWindowDays'] ?> days</div>
                        </div>
                    </div>
                </div>

            </div>

        <?php elseif ($tab['key'] === 'predictive'): ?>

            <!-- ============ FORECAST KPI TILES ============ -->
            <div class="an-forecast-tiles">
                <div class="an-forecast-tile">
                    <span class="an-forecast-tile-label">Patient Volume Forecast (Next 7 Days)</span>
                    <?php if ($patientFit['hasSignal']): ?>
                    <span class="an-forecast-tile-value"><?= number_format($patientForecastTotal) ?></span>
                    <span class="an-forecast-tile-sub <?= $patientForecastTotal >= $patientPast7Total ? 'up' : 'down' ?>">
                        <i class="bi <?= $patientForecastTotal >= $patientPast7Total ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i>
                        vs <?= number_format($patientPast7Total) ?> in the past 7 days
                    </span>
                    <?php else: ?>
                    <span class="an-forecast-tile-value">—</span>
                    <span class="an-forecast-tile-sub">Not enough history yet at this facility</span>
                    <?php endif; ?>
                </div>
                <div class="an-forecast-tile">
                    <span class="an-forecast-tile-label">Consultation Load Forecast (Next 7 Days)</span>
                    <?php if ($consultFit['hasSignal']): ?>
                    <span class="an-forecast-tile-value"><?= number_format($consultForecastTotal) ?></span>
                    <span class="an-forecast-tile-sub <?= $consultForecastTotal >= $consultPast7Total ? 'up' : 'down' ?>">
                        <i class="bi <?= $consultForecastTotal >= $consultPast7Total ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i>
                        vs <?= number_format($consultPast7Total) ?> in the past 7 days
                    </span>
                    <?php else: ?>
                    <span class="an-forecast-tile-value">—</span>
                    <span class="an-forecast-tile-sub">Not enough history yet at this facility</span>
                    <?php endif; ?>
                </div>
                <div class="an-forecast-tile">
                    <span class="an-forecast-tile-label">Projected New Patients This Month</span>
                    <span class="an-forecast-tile-value"><?= number_format($projectedMonthlyPatients) ?></span>
                    <span class="an-forecast-tile-sub <?= $projectedMonthlyGrowthPct >= 0 ? 'up' : 'down' ?>">
                        <i class="bi <?= $projectedMonthlyGrowthPct >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i>
                        <?= abs($projectedMonthlyGrowthPct) ?>% vs last month's <?= number_format($newPatientsLastMonth) ?>
                    </span>
                </div>
            </div>

            <!-- ============ FORECAST TREND CHART + RECOMMENDATIONS ============ -->
            <div class="an-forecast-row">

                <div class="an-card an-trend-card">
                    <div class="an-card-header"><h3>New Patients — Actual vs. Forecast</h3></div>
                    <div class="an-forecast-legend">
                        <span><span class="dot" style="background:#1A73E8;"></span> Actual</span>
                        <span><span class="dot dashed" style="color:#7C3AED;"></span> Forecast</span>
                    </div>
                    <div class="an-trend-canvas-box">
                        <canvas id="forecastTrendChart"></canvas>
                    </div>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>Recommendations</h3></div>
                    <?php if ($recommendations === []): ?>
                    <div class="an-reco-empty">
                        <i class="bi bi-check-circle-fill"></i>
                        <p>Nothing to flag right now at <?= htmlspecialchars($facilityName) ?> — trends and stock levels look steady.</p>
                    </div>
                    <?php else: ?>
                    <div class="an-reco-list">
                        <?php foreach ($recommendations as $r): ?>
                        <div class="an-reco-card <?= htmlspecialchars($r['type']) ?>">
                            <span class="an-reco-icon"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                            <div class="an-reco-body-wrap">
                                <div class="an-reco-title"><?= htmlspecialchars($r['title']) ?></div>
                                <p class="an-reco-body"><?= htmlspecialchars($r['body']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ============ 6.3.1–6.3.4 — THIS FACILITY'S OWN DISEASE/SHORTAGE/REPLENISHMENT/BARANGAY ANALYTICS ============ -->
            <div class="pn-section" style="margin-top:8px;">
                <div class="pn-section-head">
                    <h2><i class="bi bi-graph-up-arrow"></i> More Predictive Analytics for <?= htmlspecialchars($facilityName) ?></h2>
                    <p>Disease/demand and barangay trends are scoped to this facility's own real patients; medicine &amp; vaccine shortage stays system-wide (that inventory has no per-facility field — same honest exception the Overview tab's inventory sections already note).</p>
                </div>

                <ul class="nav adm-tabs" role="tablist">
                    <?php foreach ($predictiveSubTabs as $stab): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= !empty($stab['active']) ? 'active' : '' ?>"
                                id="faPredTab-<?= htmlspecialchars($stab['key']) ?>-btn"
                                data-bs-toggle="tab"
                                data-bs-target="#faPredTab-<?= htmlspecialchars($stab['key']) ?>"
                                type="button" role="tab">
                            <i class="bi <?= htmlspecialchars($stab['icon']) ?>"></i> <?= htmlspecialchars($stab['label']) ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content an-tab-content">

                    <!-- ===== 6.3.1 — Disease & Service Demand Forecast ===== -->
                    <div class="tab-pane fade show active" id="faPredTab-disease" role="tabpanel">
                        <div class="an-forecast-tiles">
                            <?php foreach ($pt1PredictionCards as $card): ?>
                            <div class="an-forecast-tile">
                                <span class="an-forecast-tile-label"><?= htmlspecialchars($card['label']) ?></span>
                                <span class="an-forecast-tile-value"><?= htmlspecialchars($card['value']) ?></span>
                                <span class="an-forecast-tile-sub <?= $card['trendUp'] === true ? 'up' : ($card['trendUp'] === false ? 'down' : '') ?>">
                                    <?php if ($card['trendUp'] !== null): ?><i class="bi <?= $card['trendUp'] ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i><?php endif; ?>
                                    <?= htmlspecialchars($card['sub']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="an-forecast-row">
                            <div class="an-card">
                                <div class="an-card-header"><h3>Consultations — Actual vs. Forecast</h3></div>
                                <div class="an-forecast-legend">
                                    <span><span class="dot" style="background:#1A73E8;"></span> Actual</span>
                                    <span><span class="dot dashed" style="color:#7C3AED;"></span> Forecast</span>
                                </div>
                                <div class="an-trend-canvas-box"><canvas id="faPredT1ForecastChart"></canvas></div>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Top Notifiable-Disease Categories</h3></div>
                                <p class="pn-note">Daily case counts, last 30 days, at <?= htmlspecialchars($facilityName) ?> — keyword-matched from real diagnosis/chief-complaint text.</p>
                                <?php if ($pt1DiseaseTrend['datasets'] === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No notifiable-disease cases on record here in the last 30 days.</p></div>
                                <?php else: ?>
                                <div class="an-trend-canvas-box"><canvas id="faPredT1DiseaseTrendChart"></canvas></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="an-forecast-row">
                            <div class="adm-card">
                                <div class="adm-card-header"><h3>Risk &amp; Alert Indicators</h3></div>
                                <?php if ($pt1RiskRows === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No barangay is currently under watch.</p></div>
                                <?php else: ?>
                                <div class="adm-status-list">
                                    <?php foreach ($pt1RiskRows as $r): ?>
                                    <div class="adm-status-row">
                                        <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <span class="adm-status-body">
                                            <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span></span>
                                            <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                                        </span>
                                        <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Recommendations</h3></div>
                                <?php if ($pt1Recommendations === []): ?>
                                <div class="an-reco-empty"><i class="bi bi-check-circle-fill"></i><p>Nothing to flag right now — case trends look steady.</p></div>
                                <?php else: ?>
                                <div class="an-reco-list">
                                    <?php foreach ($pt1Recommendations as $r): ?>
                                    <div class="an-reco-card <?= htmlspecialchars($r['type']) ?>">
                                        <span class="an-reco-icon"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <div class="an-reco-body-wrap">
                                            <div class="an-reco-title"><?= htmlspecialchars($r['title']) ?></div>
                                            <p class="an-reco-body"><?= htmlspecialchars($r['body']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== 6.3.2 — Medicine & Vaccine Shortage (system-wide) ===== -->
                    <div class="tab-pane fade" id="faPredTab-shortage" role="tabpanel">
                        <div class="an-forecast-tiles">
                            <?php foreach ($pt2PredictionCards as $card): ?>
                            <div class="an-forecast-tile">
                                <span class="an-forecast-tile-label"><?= htmlspecialchars($card['label']) ?></span>
                                <span class="an-forecast-tile-value"><?= htmlspecialchars($card['value']) ?></span>
                                <span class="an-forecast-tile-sub"><?= htmlspecialchars($card['sub']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="an-forecast-row">
                            <div class="an-card">
                                <div class="an-card-header"><h3>Projected Stock Decline — <?= htmlspecialchars($pt2StockProjection['itemName'] ?? 'Most Urgent Item') ?></h3></div>
                                <p class="pn-note">System-wide stock/order pace — inventory has no per-facility field in this app's schema.</p>
                                <?php if ($pt2StockProjection['itemName'] === null): ?>
                                <div class="pn-empty"><i class="bi bi-info-circle-fill"></i><p>No item currently has enough recent order history to project a decline curve.</p></div>
                                <?php else: ?>
                                <div class="an-forecast-legend">
                                    <span><span class="dot dashed" style="color:#D93025;"></span> Projected stock</span>
                                    <span><span class="dot dashed" style="color:#E8710A;"></span> Reorder level</span>
                                </div>
                                <div class="an-trend-canvas-box"><canvas id="faPredT2StockProjectionChart"></canvas></div>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Order/Prescription Frequency — Top Items</h3></div>
                                <?php if ($pt2OrderFrequency['datasets'] === []): ?>
                                <div class="pn-empty"><i class="bi bi-info-circle-fill"></i><p>No real pharmacy orders or prescriptions on record in the last 30 days.</p></div>
                                <?php else: ?>
                                <div class="an-trend-canvas-box"><canvas id="faPredT2OrderFreqChart"></canvas></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="an-card">
                            <div class="an-card-header"><h3>Projected Stock Decline — All At-Risk Items</h3></div>
                            <p class="pn-note">Every medicine/vaccine currently projected to stock out within 30 days, side by side — not just the single most urgent one above.</p>
                            <?php if ($pt2ShortageTrend['datasets'] === []): ?>
                            <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No medicine or vaccine is currently projected to run low.</p></div>
                            <?php else: ?>
                            <div class="an-trend-canvas-box"><canvas id="faPredT2ShortageTrendChart"></canvas></div>
                            <?php endif; ?>
                        </div>
                        <div class="an-forecast-row">
                            <div class="adm-card">
                                <div class="adm-card-header"><h3>Risk &amp; Alert Indicators</h3></div>
                                <?php if ($pt2RiskRows === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No medicine or vaccine is currently at risk.</p></div>
                                <?php else: ?>
                                <div class="adm-status-list">
                                    <?php foreach ($pt2RiskRows as $r): ?>
                                    <div class="adm-status-row">
                                        <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <span class="adm-status-body">
                                            <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span></span>
                                            <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                                        </span>
                                        <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Recommendations</h3></div>
                                <?php if ($pt2Recommendations === []): ?>
                                <div class="an-reco-empty"><i class="bi bi-check-circle-fill"></i><p>Nothing to flag right now — stock levels look steady.</p></div>
                                <?php else: ?>
                                <div class="an-reco-list">
                                    <?php foreach ($pt2Recommendations as $r): ?>
                                    <div class="an-reco-card <?= htmlspecialchars($r['type']) ?>">
                                        <span class="an-reco-icon"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <div class="an-reco-body-wrap">
                                            <div class="an-reco-title"><?= htmlspecialchars($r['title']) ?></div>
                                            <p class="an-reco-body"><?= htmlspecialchars($r['body']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== 6.3.3 — Inventory Replenishment (stock_requests scoped to this facility) ===== -->
                    <div class="tab-pane fade" id="faPredTab-replenishment" role="tabpanel">
                        <div class="an-forecast-tiles">
                            <?php foreach ($pt3PredictionCards as $card): ?>
                            <div class="an-forecast-tile">
                                <span class="an-forecast-tile-label"><?= htmlspecialchars($card['label']) ?></span>
                                <span class="an-forecast-tile-value"><?= htmlspecialchars($card['value']) ?></span>
                                <span class="an-forecast-tile-sub"><?= htmlspecialchars($card['sub']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="an-forecast-row">
                            <div class="an-card">
                                <div class="an-card-header"><h3>Reorder Urgency — Top Items</h3></div>
                                <?php if ($pt3RankedItems === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>Nothing needs reordering right now.</p></div>
                                <?php else: ?>
                                <div class="an-inv-list-header"><span>Days Left</span></div>
                                <?php foreach ($pt3RankedItems as $item): ?>
                                <div class="an-inv-row">
                                    <span class="an-inv-row-name"><?= htmlspecialchars($item['name']) ?></span>
                                    <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($item['currentStock'] / $pt3RankedMax * 100) ?>%;"></div></div>
                                    <span class="an-inv-row-value"><?= $item['alreadyOut'] ? 'Out' : (int) $item['daysUntilStockout'] ?></span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Weekly Stock Request Volume — <?= htmlspecialchars($facilityName) ?></h3></div>
                                <?php if ($pt3RequestVolume['labels'] === []): ?>
                                <div class="pn-empty"><i class="bi bi-info-circle-fill"></i><p>No stock requests on record for this facility.</p></div>
                                <?php else: ?>
                                <div class="an-trend-canvas-box"><canvas id="faPredT3RequestVolumeChart"></canvas></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="an-forecast-row">
                            <div class="adm-card">
                                <div class="adm-card-header"><h3>Risk &amp; Alert Indicators</h3></div>
                                <?php if ($pt3RiskRows === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>Every at-risk item already has a reorder in the pipeline.</p></div>
                                <?php else: ?>
                                <div class="adm-status-list">
                                    <?php foreach ($pt3RiskRows as $r): ?>
                                    <div class="adm-status-row">
                                        <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <span class="adm-status-body">
                                            <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span></span>
                                            <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                                        </span>
                                        <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Recommendations</h3></div>
                                <?php if ($pt3Recommendations === []): ?>
                                <div class="an-reco-empty"><i class="bi bi-check-circle-fill"></i><p>Nothing to flag right now — replenishment looks on track.</p></div>
                                <?php else: ?>
                                <div class="an-reco-list">
                                    <?php foreach ($pt3Recommendations as $r): ?>
                                    <div class="an-reco-card <?= htmlspecialchars($r['type']) ?>">
                                        <span class="an-reco-icon"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <div class="an-reco-body-wrap">
                                            <div class="an-reco-title"><?= htmlspecialchars($r['title']) ?></div>
                                            <p class="an-reco-body"><?= htmlspecialchars($r['body']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== 6.3.4 — Consultation Trends by Barangay ===== -->
                    <div class="tab-pane fade" id="faPredTab-barangay" role="tabpanel">
                        <div class="an-forecast-tiles" style="grid-template-columns:repeat(2,1fr);">
                            <?php foreach ($pt4PredictionCards as $card): ?>
                            <div class="an-forecast-tile">
                                <span class="an-forecast-tile-label"><?= htmlspecialchars($card['label']) ?></span>
                                <span class="an-forecast-tile-value"><?= htmlspecialchars($card['value']) ?></span>
                                <span class="an-forecast-tile-sub"><?= htmlspecialchars($card['sub']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="an-forecast-row">
                            <div class="an-card">
                                <div class="an-card-header"><h3>This Facility's Patients — Actual vs. Forecast</h3></div>
                                <div class="an-forecast-legend">
                                    <span><span class="dot" style="background:#1A73E8;"></span> Actual</span>
                                    <span><span class="dot dashed" style="color:#7C3AED;"></span> Forecast</span>
                                </div>
                                <div class="an-trend-canvas-box"><canvas id="faPredT4ForecastChart"></canvas></div>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Consultations by Barangay (Last 30 Days)</h3></div>
                                <p class="pn-note">This facility's own patients only, restricted to the 11 real barangays this clinic covers.</p>
                                <div class="an-inv-list-header"><span>Consultations</span></div>
                                <?php foreach ($pt4Barangays as $b): ?>
                                <div class="an-inv-row">
                                    <span class="an-inv-row-name"><?= htmlspecialchars($b['barangay']) ?></span>
                                    <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($b['total30'] / $pt4Max * 100) ?>%;"></div></div>
                                    <span class="an-inv-row-value"><?= (int) $b['total30'] ?><i class="bi pn-trend-arrow <?= $b['arrow'] ?> bi-arrow-<?= $b['arrow'] === 'flat' ? 'right' : $b['arrow'] ?>-short"></i></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="an-forecast-row">
                            <div class="adm-card">
                                <div class="adm-card-header"><h3>Risk &amp; Alert Indicators</h3></div>
                                <?php if ($pt4RiskRows === []): ?>
                                <div class="pn-empty"><i class="bi bi-check-circle-fill"></i><p>No barangay shows a significant rising trend right now.</p></div>
                                <?php else: ?>
                                <div class="adm-status-list">
                                    <?php foreach ($pt4RiskRows as $r): ?>
                                    <div class="adm-status-row">
                                        <span class="adm-status-icon" style="background:<?= htmlspecialchars($r['iconBg']) ?>; color:<?= htmlspecialchars($r['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <span class="adm-status-body">
                                            <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span></span>
                                            <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                                        </span>
                                        <span class="adm-status-number" style="color:<?= htmlspecialchars($r['valueColor']) ?>;"><?= htmlspecialchars((string) $r['value']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="an-card">
                                <div class="an-card-header"><h3>Recommendations</h3></div>
                                <?php if ($pt4Recommendations === []): ?>
                                <div class="an-reco-empty"><i class="bi bi-check-circle-fill"></i><p>Nothing to flag right now — demand looks steady across covered barangays.</p></div>
                                <?php else: ?>
                                <div class="an-reco-list">
                                    <?php foreach ($pt4Recommendations as $r): ?>
                                    <div class="an-reco-card <?= htmlspecialchars($r['type']) ?>">
                                        <span class="an-reco-icon"><i class="bi <?= htmlspecialchars($r['icon']) ?>"></i></span>
                                        <div class="an-reco-body-wrap">
                                            <div class="an-reco-title"><?= htmlspecialchars($r['title']) ?></div>
                                            <p class="an-reco-body"><?= htmlspecialchars($r['body']) ?></p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        <?php elseif ($tab['key'] === 'patients'): ?>

            <div class="an-summary-tiles" style="margin-bottom:20px;">
                <div class="an-summary-tile"><span class="an-tile-label">Total Patients</span><span class="an-tile-value"><?= number_format($totalPatients) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Male</span><span class="an-tile-value"><?= number_format($patientsMaleCount) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Female</span><span class="an-tile-value"><?= number_format($patientsFemaleCount) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Average Age</span><span class="an-tile-value"><?= $patientsAverageAge ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1.6fr;">
                <div class="an-card">
                    <div class="an-card-header"><h3>Patients by Barangay</h3></div>
                    <?php if ($patientsByBarangay === []): ?>
                    <p class="adm-table-empty"><?= $totalPatients > 0 ? 'None of this facility\'s patients have a barangay on file yet.' : 'No patients registered at this facility yet.' ?></p>
                    <?php else: ?>
                    <div class="an-inv-list-header"><span>Patients</span></div>
                    <?php foreach ($patientsByBarangay as $b): ?>
                    <div class="an-inv-row">
                        <span class="an-inv-row-name"><?= htmlspecialchars($b['label']) ?></span>
                        <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($b['value'] / $patientsByBarangayMax * 100) ?>%;"></div></div>
                        <span class="an-inv-row-value"><?= number_format($b['value']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>Recently Registered</h3></div>
                    <?php if ($recentPatientsTable === []): ?>
                    <p class="adm-table-empty">No patients registered at this facility yet.</p>
                    <?php else: ?>
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead><tr><th>Name</th><th>Age</th><th>Gender</th><th>Barangay</th><th>Registered</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentPatientsTable as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= (int) $p['age'] ?></td>
                                    <td><?= htmlspecialchars($p['gender']) ?></td>
                                    <td><?= htmlspecialchars($p['barangay'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'records'): ?>

            <div class="an-summary-tiles" style="margin-bottom:20px;">
                <div class="an-summary-tile"><span class="an-tile-label">Total Records</span><span class="an-tile-value"><?= number_format($recordsTotal) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">This Month</span><span class="an-tile-value"><?= number_format($recordsThisMonth) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Consultation Records</span><span class="an-tile-value"><?= number_format($recordsConsultationCount) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Laboratory Records</span><span class="an-tile-value"><?= number_format($recordsLaboratoryCount) ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1.6fr;">
                <div class="an-card">
                    <div class="an-card-header"><h3>Records by Type</h3></div>
                    <?php if ($recordsByTypeTotal > 0): ?>
                    <div class="an-donut-wrap">
                        <div class="an-donut-canvas-box">
                            <canvas id="recordsByTypeChart"></canvas>
                            <div class="an-donut-center"><div class="an-donut-total"><?= number_format($recordsByTypeTotal) ?></div><div class="an-donut-total-label">Total</div></div>
                        </div>
                    </div>
                    <div class="an-donut-legend">
                        <?php foreach ($recordsByType as $rt): ?>
                        <div class="legend-row">
                            <span class="dot" style="background:<?= htmlspecialchars($rt['color']) ?>;"></span>
                            <span class="legend-label"><?= htmlspecialchars($rt['label']) ?></span>
                            <span class="legend-value"><?= number_format($rt['value']) ?> (<?= rtrim(rtrim(number_format($rt['value'] / $recordsByTypeTotal * 100, 1), '0'), '.') ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="adm-table-empty">No medical records at this facility yet.</p>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>Recent Records</h3></div>
                    <?php if ($recentRecordsTable === []): ?>
                    <p class="adm-table-empty">No medical records at this facility yet.</p>
                    <?php else: ?>
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead><tr><th>Date</th><th>Patient</th><th>Type</th><th>Title</th><th>Provider</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentRecordsTable as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['date']) ?></td>
                                    <td><?= htmlspecialchars($r['patientName']) ?></td>
                                    <td><?= htmlspecialchars($r['type']) ?></td>
                                    <td><?= htmlspecialchars($r['title']) ?></td>
                                    <td><?= htmlspecialchars($r['provider']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'services'): ?>

            <div class="an-summary-tiles" style="margin-bottom:20px;">
                <div class="an-summary-tile"><span class="an-tile-label">Total Consultations</span><span class="an-tile-value"><?= number_format($consultationsTotal) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">This Month</span><span class="an-tile-value"><?= number_format($consultationsThisMonth) ?></span></div>
                <div class="an-summary-tile good"><span class="an-tile-label">Completed</span><span class="an-tile-value"><?= number_format($consultationsCompleted) ?></span></div>
                <div class="an-summary-tile warn"><span class="an-tile-label">Pending</span><span class="an-tile-value"><?= number_format($consultationsPending) ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1.6fr;">
                <div class="an-card">
                    <div class="an-card-header"><h3>Consultations by Status</h3></div>
                    <?php if ($servicesByStatusTotal > 0): ?>
                    <div class="an-donut-wrap">
                        <div class="an-donut-canvas-box">
                            <canvas id="servicesByStatusChart"></canvas>
                            <div class="an-donut-center"><div class="an-donut-total"><?= number_format($servicesByStatusTotal) ?></div><div class="an-donut-total-label">Total</div></div>
                        </div>
                    </div>
                    <div class="an-donut-legend">
                        <?php foreach ($servicesByStatus as $s): ?>
                        <div class="legend-row">
                            <span class="dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                            <span class="legend-label"><?= htmlspecialchars($s['label']) ?></span>
                            <span class="legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['value'] / $servicesByStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="adm-table-empty">No consultations at this facility yet.</p>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>All Service Types</h3></div>
                    <?php if ($servicesTable === []): ?>
                    <p class="adm-table-empty">No consultations at this facility yet.</p>
                    <?php else: ?>
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead><tr><th>Service Type</th><th>Count</th><th>Share</th></tr></thead>
                            <tbody>
                                <?php foreach ($servicesTable as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['label']) ?></td>
                                    <td><?= number_format($s['value']) ?></td>
                                    <td><?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'staff'): ?>

            <div class="an-summary-tiles" style="margin-bottom:20px; grid-template-columns:repeat(3,1fr);">
                <div class="an-summary-tile"><span class="an-tile-label">Total Staff</span><span class="an-tile-value"><?= number_format($facilityStaffCount) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Doctors</span><span class="an-tile-value"><?= number_format($staffRoleTallies['doctor'] ?? 0) ?></span></div>
                <div class="an-summary-tile"><span class="an-tile-label">Medical Staff</span><span class="an-tile-value"><?= number_format($staffRoleTallies['healthcare_staff'] ?? 0) ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1.6fr;">
                <div class="an-card">
                    <div class="an-card-header"><h3>Staff by Role</h3></div>
                    <?php if ($staffByRoleTotal > 0): ?>
                    <div class="an-donut-wrap">
                        <div class="an-donut-canvas-box">
                            <canvas id="staffByRoleChart"></canvas>
                            <div class="an-donut-center"><div class="an-donut-total"><?= number_format($staffByRoleTotal) ?></div><div class="an-donut-total-label">Total</div></div>
                        </div>
                    </div>
                    <div class="an-donut-legend">
                        <?php foreach ($staffByRole as $s): ?>
                        <div class="legend-row">
                            <span class="dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                            <span class="legend-label"><?= htmlspecialchars($s['label']) ?></span>
                            <span class="legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['value'] / $staffByRoleTotal * 100, 1), '0'), '.') ?>%)</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="adm-table-empty">No staff assigned to this facility yet.</p>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>Staff Directory</h3></div>
                    <?php if ($staffTable === []): ?>
                    <p class="adm-table-empty">No staff assigned to this facility yet.</p>
                    <?php else: ?>
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($staffTable as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= htmlspecialchars($s['role']) ?></td>
                                    <td><?= htmlspecialchars($s['email']) ?></td>
                                    <td><span class="an-status-badge" <?= $s['status'] !== 'Active' ? 'style="background:var(--adm-orange-bg);color:var(--adm-orange);"' : '' ?>><?= htmlspecialchars($s['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'medicines'): ?>

            <div class="an-inv-pills" style="margin-bottom:20px;">
                <div class="an-inv-pill"><span class="an-inv-pill-label">Total Medicines</span><span class="an-inv-pill-value"><?= number_format($medicineInventory['total']) ?></span></div>
                <div class="an-inv-pill good"><span class="an-inv-pill-label">In Stock</span><span class="an-inv-pill-value"><?= number_format($medicineInventory['inStock']) ?></span></div>
                <div class="an-inv-pill warn"><span class="an-inv-pill-label">Low Stock</span><span class="an-inv-pill-value"><?= number_format($medicineInventory['lowStock']) ?></span></div>
                <div class="an-inv-pill bad"><span class="an-inv-pill-label">Out of Stock</span><span class="an-inv-pill-value"><?= number_format($medicineInventory['outOfStock']) ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1.6fr;">
                <div class="an-card">
                    <div class="an-card-header"><h3>Medicines by Category</h3></div>
                    <?php if ($medicinesByCategory === []): ?>
                    <p class="adm-table-empty">No medicines on record.</p>
                    <?php else: ?>
                    <div class="an-inv-list-header"><span>Items</span></div>
                    <?php foreach ($medicinesByCategory as $c): ?>
                    <div class="an-inv-row">
                        <span class="an-inv-row-name"><?= htmlspecialchars($c['label']) ?></span>
                        <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($c['value'] / $medicinesByCategoryMax * 100) ?>%;"></div></div>
                        <span class="an-inv-row-value"><?= number_format($c['value']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header">
                        <h3>Medicines</h3>
                        <span class="an-card-header-note">System-wide inventory</span>
                    </div>
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead><tr><th>Name</th><th>Category</th><th>Stock</th><th>Status</th><th>Expiry</th></tr></thead>
                            <tbody>
                                <?php foreach ($medicinesTable as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['name']) ?></td>
                                    <td><?= htmlspecialchars($m['category']) ?></td>
                                    <td><?= number_format($m['stock']) ?></td>
                                    <td><span class="an-status-badge" style="background:<?= htmlspecialchars($m['status']['bg']) ?>;color:<?= htmlspecialchars($m['status']['color']) ?>;"><?= htmlspecialchars($m['status']['label']) ?></span></td>
                                    <td><?= htmlspecialchars($m['expiry']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'vaccines'): ?>

            <div class="an-inv-pills" style="margin-bottom:20px;">
                <div class="an-inv-pill"><span class="an-inv-pill-label">Total Vaccines</span><span class="an-inv-pill-value"><?= number_format($vaccineInventory['total']) ?></span></div>
                <div class="an-inv-pill good"><span class="an-inv-pill-label">In Stock</span><span class="an-inv-pill-value"><?= number_format($vaccineInventory['inStock']) ?></span></div>
                <div class="an-inv-pill warn"><span class="an-inv-pill-label">Low Stock</span><span class="an-inv-pill-value"><?= number_format($vaccineInventory['lowStock']) ?></span></div>
                <div class="an-inv-pill bad"><span class="an-inv-pill-label">Out of Stock</span><span class="an-inv-pill-value"><?= number_format($vaccineInventory['outOfStock']) ?></span></div>
            </div>

            <div class="an-charts-row" style="grid-template-columns:1fr 1fr;">
                <div class="an-card">
                    <div class="an-card-header">
                        <h3>Immunizations Given</h3>
                        <span class="an-card-header-note">At this facility</span>
                    </div>
                    <?php if ($immunizationsByVaccineTable === []): ?>
                    <p class="adm-table-empty">No immunizations recorded at this facility yet.</p>
                    <?php else: ?>
                    <p style="font-size:12.5px;color:var(--adm-text-muted);margin:0 0 10px;"><?= number_format($immunizationsTotal) ?> total completed immunizations, all-time.</p>
                    <div class="an-inv-list-header"><span>Doses</span></div>
                    <?php foreach ($immunizationsByVaccineTable as $v): ?>
                    <div class="an-inv-row">
                        <span class="an-inv-row-name"><?= htmlspecialchars($v['label']) ?></span>
                        <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($v['value'] / $immunizationsByVaccineMax * 100) ?>%;"></div></div>
                        <span class="an-inv-row-value"><?= number_format($v['value']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="an-card">
                    <div class="an-card-header"><h3>Vaccines by Category</h3></div>
                    <?php if ($vaccinesByCategory === []): ?>
                    <p class="adm-table-empty">No vaccines on record.</p>
                    <?php else: ?>
                    <div class="an-inv-list-header"><span>Items</span></div>
                    <?php foreach ($vaccinesByCategory as $c): ?>
                    <div class="an-inv-row">
                        <span class="an-inv-row-name"><?= htmlspecialchars($c['label']) ?></span>
                        <div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:<?= round($c['value'] / $vaccinesByCategoryMax * 100) ?>%;"></div></div>
                        <span class="an-inv-row-value"><?= number_format($c['value']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="an-card" style="margin-top:18px;">
                <div class="an-card-header">
                    <h3>Vaccines</h3>
                    <span class="an-card-header-note">System-wide inventory</span>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead><tr><th>Name</th><th>Category</th><th>Stock</th><th>Status</th><th>Expiry</th></tr></thead>
                        <tbody>
                            <?php foreach ($vaccinesTable as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['name']) ?></td>
                                <td><?= htmlspecialchars($v['category']) ?></td>
                                <td><?= number_format($v['stock']) ?></td>
                                <td><span class="an-status-badge" style="background:<?= htmlspecialchars($v['status']['bg']) ?>;color:<?= htmlspecialchars($v['status']['color']) ?>;"><?= htmlspecialchars($v['status']['label']) ?></span></td>
                                <td><?= htmlspecialchars($v['expiry']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($tab['key'] === 'reports'): ?>

            <div class="an-card">
                <div class="an-card-header">
                    <h3>Month-over-Month Summary</h3>
                    <span class="an-card-header-note"><?= date('F Y') ?> vs <?= date('F Y', strtotime('first day of last month')) ?></span>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead><tr><th>Metric</th><th>This Month</th><th>Last Month</th><th>Change</th></tr></thead>
                        <tbody>
                            <?php foreach ($monthlyReport as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['metric']) ?></td>
                                <td><?= number_format($row['thisMonth']) ?></td>
                                <td><?= number_format($row['lastMonth']) ?></td>
                                <td>
                                    <span style="color:<?= $row['changePct'] >= 0 ? 'var(--adm-green)' : 'var(--adm-red)' ?>; font-weight:700;">
                                        <i class="bi <?= $row['changePct'] >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i><?= abs($row['changePct']) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="an-tab-placeholder">
                <i class="bi bi-cone-striped"></i>
                <p>The "<?= htmlspecialchars($tab['label']) ?>" section isn't built yet — check back soon.</p>
            </div>
        <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    // Hands all chart numbers to assets/js/facility_analytics.js without it
    // needing to parse PHP-rendered HTML — same pattern as admin_dashboard.php.
    window.FACILITY_ANALYTICS_DATA = <?= json_encode([
        'summaryCards' => array_map(fn ($c) => [
            'key' => $c['key'],
            'sparkline' => $c['sparkline'],
            'color' => $c['sparklineColor'],
        ], $summaryCards),
        'patientTrend' => $patientTrend,
        'demographics' => [
            'labels' => array_column($demographics, 'label'),
            'values' => array_column($demographics, 'value'),
            'colors' => array_column($demographics, 'color'),
        ],
        // The Card variants (not the unfiltered $medicineStockDistribution/
        // $vaccineStockDistribution — those feed Inventory Summary/
        // recommendations instead, see includes/admin/facility_analytics_firestore.php's
        // comment) — the donut on first paint must match whatever
        // ?medCategory=/?vacCategory= the URL already carries (e.g. a
        // reload, or the no-JS fallback link), not the system-wide total.
        'medicineStockDistribution' => [
            'labels' => array_column($medicineStockDistributionCard, 'label'),
            'values' => array_column($medicineStockDistributionCard, 'pct'),
            'colors' => array_column($medicineStockDistributionCard, 'color'),
        ],
        'vaccineStockDistribution' => [
            'labels' => array_column($vaccineStockDistributionCard, 'label'),
            'values' => array_column($vaccineStockDistributionCard, 'pct'),
            'colors' => array_column($vaccineStockDistributionCard, 'color'),
        ],
        'forecastTrend' => $forecastTrend,
        'recordsByType' => [
            'labels' => array_column($recordsByType, 'label'),
            'values' => array_column($recordsByType, 'value'),
            'colors' => array_column($recordsByType, 'color'),
        ],
        'servicesByStatus' => [
            'labels' => array_column($servicesByStatus, 'label'),
            'values' => array_column($servicesByStatus, 'value'),
            'colors' => array_column($servicesByStatus, 'color'),
        ],
        'staffByRole' => [
            'labels' => array_column($staffByRole, 'label'),
            'values' => array_column($staffByRole, 'value'),
            'colors' => array_column($staffByRole, 'color'),
        ],
        // The 4 additional per-facility predictive categories (6.3.1–6.3.4),
        // computed by includes/admin/facility_analytics_firestore.php via
        // includes/shared/predictive_analytics_core.php — rendered by
        // window.HCPredictiveCharts.renderAll() (assets/js/predictive-analytics.js)
        // with idPrefix "faPred" — see this page's own <script> block below.
        'predictive' => [
            't1ForecastTrend' => $pt1ForecastTrend,
            't1DiseaseTrend' => $pt1DiseaseTrend,
            't2StockProjection' => $pt2StockProjection,
            't2OrderFrequency' => $pt2OrderFrequency,
            't2ShortageTrend' => $pt2ShortageTrend,
            't3RequestVolume' => $pt3RequestVolume,
            't4ForecastTrend' => $pt4ForecastTrend,
        ],
    ]) ?>;
</script>
<!-- Predictive Analytics charts (6.3.1–6.3.4) — same renderer facility_management.php uses -->
<script src="<?= asset_url('/assets/js/predictive-analytics.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.FACILITY_ANALYTICS_DATA && window.FACILITY_ANALYTICS_DATA.predictive && window.HCPredictiveCharts) {
            window.HCPredictiveCharts.renderAll(window.FACILITY_ANALYTICS_DATA.predictive, 'faPred');
        }
    });
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/facility_analytics.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/live-clock.js') ?>"></script>
</body>
</html>
