<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Sub-Admin Dashboard (subadmin/dashboard.php)
 * ---------------------------------------------------------------------
 * This is the landing dashboard for the Sub-Admin role — a separate
 * section from admin/*.php (the System Administrator's facility/
 * sub-admin oversight pages). It reuses the exact same visual shell
 * (280px dark-red gradient sidebar, card/table/donut atoms, all
 * "adm-" prefixed, assets/css/admin-dashboard.css) so the brand reads
 * as one system, but with its OWN nav list scoped to a single
 * facility's day-to-day operations (Patient Management, Stock
 * Requests, ...) instead of the System Administrator's.
 *
 * PARTIALLY connected to real Firestore data — Total Patients, Patients
 * by Age Group, Patients by Gender, Active Medical Staff, Low Stock
 * Alerts, and Recent Stock Requests are all real now (see
 * includes/subadmin/subadmin_dashboard_firestore.php's header comment for exactly
 * which collections). Today's Consultations, Total Medical Records,
 * the Consultations Trend chart, Top 5 Health Conditions,
 * Consultations by Type, Lab/X-ray Summary, and Monthly Consultation
 * Comparison stay front-end only — there's no consultations/medical-
 * records/lab-result data model anywhere in this app, and building one
 * is out of scope for this pass.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in sub-admin may view this page.
if (($_SESSION['role'] ?? null) !== 'subadmin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/subadmin/subadmin_dashboard_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $consultationsTrendRange, $consultationsTrend, $patientsByAgeRange,
// $patientsByAge, $patientsByGenderTotal, $patientsByGender,
// $healthConditionsRange, $topHealthConditions, $topHealthConditionsMax

require_once __DIR__ . '/../includes/subadmin/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTopAlerts — this page's
// own predictive preview card ONLY, kept as its own require here rather
// than chained inside subadmin_dashboard_firestore.php, so that file
// stays exactly what it was (no risk of it silently gaining an
// unrelated heavy computation nothing else that requires it wants).

// Sidebar nav — Dashboard is active on this page. None of these other
// pages are built yet; each link is ready for when they are.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php', 'active' => true],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php'],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php'],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php'],
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard page styles (stat cards, chart cards, condition/stock lists, table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
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
            <p><?= htmlspecialchars(t('app.tagline')) ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Sub-admin profile -->
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
            <a href="#<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
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
                <span class="sad-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span><?= htmlspecialchars(t('nav.logout')) ?></span>
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
            <h1><?= htmlspecialchars(t('nav.dashboard')) ?></h1>
            <p class="adm-page-subtitle"><?= htmlspecialchars(t('page.subadmin_dashboard.welcome_prefix')) ?><?= htmlspecialchars($adminProfile['name']) ?><?= htmlspecialchars(t('page.subadmin_dashboard.welcome_suffix')) ?></p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this sub-admin is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a sub-admin can't reassign themselves. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($adminProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (subadmin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= subadmin_unread_notification_count() ?></span><?php endif; ?>
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
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars(t('nav.logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="sad-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="sad-stat-card">
            <div class="sad-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="sad-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="sad-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if (isset($stat['sub'])): ?>
            <div class="sad-stat-sub <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
            </div>
            <?php elseif (isset($stat['breakdown'])): ?>
            <div class="sad-stat-breakdown">
                <?php foreach ($stat['breakdown'] as $b): ?>
                <span><?= htmlspecialchars($b['label']) ?>: <?= (int) $b['value'] ?></span>
                <?php endforeach; ?>
            </div>
            <?php elseif (isset($stat['link'])): ?>
            <a href="<?= htmlspecialchars($stat['link']['href']) ?>" class="sad-stat-link"><?= htmlspecialchars($stat['link']['label']) ?> <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         Locked to this sub-admin's own facility — see
         includes/subadmin/predictive_overview_firestore.php's header
         comment. The full breakdown (4 categories, 6.3.1–6.3.4) is one
         click away via "View Full Predictive & Prescriptive Analytics". -->
    <?php
    $predictiveOverviewLinkHref = 'predictive_analytics.php';
    $predictiveOverviewLinkLabel = 'View Full Predictive & Prescriptive Analytics';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ CONSULTATIONS TREND + AGE GROUP + GENDER ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card" data-dashboard-card="consultationsTrend" data-range-param="trendRange">
            <div class="sad-card-header">
                <h2>Consultations Trend</h2>
                <?php $range = $consultationsTrendRangeState; $drBtnClass = 'sad-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
            </div>
            <div class="sad-canvas-box">
                <canvas id="consultationsTrendChart"></canvas>
            </div>
            <div class="sad-chart-legend">
                <span><span class="dot" style="background:#D93025;"></span> Consultations</span>
            </div>
        </div>

        <div class="adm-card sad-chart-card" data-dashboard-card="patientsByAge" data-range-param="ageRange">
            <div class="sad-card-header">
                <h2>Patients by Age Group</h2>
                <?php $range = $patientsByAgeRangeState; $drBtnClass = 'sad-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
            </div>
            <div class="sad-canvas-box">
                <canvas id="patientsByAgeChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Patients by Gender</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="patientsByGenderChart"></canvas>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($patientsByGender as $g): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($g['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($g['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($g['value']) ?> (<?= rtrim(rtrim(number_format($g['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
                <div class="sad-legend-total">
                    <span>Total</span>
                    <span><?= number_format($patientsByGenderTotal) ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- ============ HEALTH CONDITIONS ============ -->
    <div class="adm-card sad-chart-card" data-dashboard-card="healthConditions" data-range-param="conditionsRange">
        <div class="sad-card-header">
            <h2>Top 5 Common Health Conditions</h2>
            <?php $range = $healthConditionsRangeState; $drBtnClass = 'sad-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
        </div>
        <div data-card-list-wrap>
            <?php require __DIR__ . '/../includes/subadmin/_dashboard_health_conditions_list.php'; ?>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    // Hands all chart numbers to assets/js/subadmin_dashboard.js without it
    // needing to parse PHP-rendered HTML — same pattern as the other
    // Chart.js-driven admin pages.
    window.SUBADMIN_DASHBOARD_DATA = <?= json_encode([
        'consultationsTrend' => $consultationsTrend,
        'patientsByAge' => $patientsByAge,
        'patientsByGender' => [
            'labels' => array_column($patientsByGender, 'label'),
            'values' => array_column($patientsByGender, 'value'),
            'colors' => array_column($patientsByGender, 'color'),
        ],
    ]) ?>;
    window.HC_DASHBOARD_CARD_ENDPOINT = '../api/subadmin-dashboard-card.php';
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/subadmin_dashboard.js') ?>"></script>
<script src="<?= asset_url('/assets/js/dashboard-range-filters.js') ?>"></script>
</body>
</html>
