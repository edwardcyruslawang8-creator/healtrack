<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Predictive & Prescriptive Analytics (subadmin/predictive_analytics.php)
 * ---------------------------------------------------------------------
 * The "Predictive & Prescriptive" tab of the Analytics & Reports
 * section — a sibling page to subadmin/analytics_reports.php
 * ("Overview"), reached via that page's report tabs (and vice versa),
 * same shape as subadmin/patient_reports.php etc. Reuses the exact
 * same shell as the rest of subadmin/*.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css), the
 * same breadcrumb/tabs styling as analytics_reports.php
 * ("arp-" prefixed, assets/css/analytics_reports.css) — plus
 * admin/facility_analytics.php's own "an-"/"pn-" prefixed Predictive &
 * Prescriptive markup (assets/css/facility_analytics.css,
 * assets/css/predictive_analytics.css), copied here verbatim since the
 * underlying data (includes/subadmin/predictive_analytics_firestore.php)
 * computes the exact same real variables.
 *
 * CONNECTED to real Firestore data — the exact same computation admin's
 * own "Predictive & Prescriptive" tab uses for this same facility (see
 * includes/subadmin/predictive_analytics_firestore.php's header
 * comment), hard-LOCKED to this sub-admin's own real assigned facility
 * — no picker, and any facility_id a request might carry is ignored/
 * overridden (same boundary api/get-patient-trend.php's subadmin
 * branch also enforces).
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

require_once __DIR__ . '/../includes/subadmin/predictive_analytics_firestore.php';
// $subadminFacilityUnresolved, $adminProfile, $analyticsDateRangeLabel, $analyticsTabs,
// $facility, $facilityName, $patientFit, $consultFit, $patientForecastTotal,
// $consultForecastTotal, $patientPast7Total, $consultPast7Total, $projectedMonthlyPatients,
// $projectedMonthlyGrowthPct, $newPatientsLastMonth, $forecastTrend, $recommendations,
// $pt1PredictionCards..$pt4Recommendations (the 4 categories, 6.3.1–6.3.4)

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Analytics & Reports stays active — this page is one of its tabs.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php', 'active' => true],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php'],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_subadmin'),           'href' => 'audit_logs.php?role=subadmin'],
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_radiotech'), 'href' => 'audit_logs.php?role=radio_technologist'],
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
<title>Predictive & Prescriptive — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin shell overrides (nav badge, topbar select buttons, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<!-- admin/facility_analytics.php's own Predictive & Prescriptive markup styles ("an-" prefixed) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/facility_analytics.css') ?>">
<!-- Predictive Analytics styles (the 4 additional 6.3.1–6.3.4 categories, "pn-" prefixed) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/predictive_analytics.css') ?>">
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR — reused verbatim from analytics_reports.php / dashboard.php.
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

    <?php if ($subadminFacilityUnresolved): ?>
    <div class="alert alert-danger py-2 px-3 small" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Your account isn't assigned to a real facility yet — contact your System Administrator to get this fixed before analytics can show here.
    </div>
    <?php endif; ?>

    <!-- ============ TOP HEADER ============ -->
    <div class="adm-topbar">
        <div>
            <h1><?= htmlspecialchars(t('nav.analytics_reports')) ?></h1>
            <nav class="arp-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <a href="analytics_reports.php">Analytics &amp; Reports</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <span class="arp-breadcrumb-current">Predictive &amp; Prescriptive</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($analyticsDateRangeLabel) ?>
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

    <!-- ============ REPORT TABS ============ -->
    <ul class="nav arp-tabs" role="tablist">
        <?php foreach ($analyticsTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <a href="<?= htmlspecialchars($tab['href']) ?>" class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" data-ajax-tab>
                <?= htmlspecialchars($tab['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- ============ SECTION INTRO ============ -->
    <div style="margin-bottom:18px;">
        <h2 style="font-size:19px; font-weight:700; color:var(--adm-text); margin:0 0 4px;">Predictive &amp; Prescriptive Analytics</h2>
        <p style="font-size:13px; color:var(--adm-text-muted); margin:0;">Real forecasts and rule-based recommendations for <?= htmlspecialchars($facilityName) ?> — disease &amp; service-demand forecast, medicine &amp; vaccine shortage, inventory replenishment, and consultation trends by barangay.</p>
    </div>

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
            <p>Disease/demand and barangay trends are scoped to this facility's own real patients; medicine &amp; vaccine shortage stays system-wide (that inventory has no per-facility field — same honest exception the app's inventory sections already note).</p>
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
                <?php if ($pt1ConsultVolumeAnomaly): ?>
                <p class="pn-anomaly-note"><span class="pn-severity unusual">Unusual</span> Consultation volume over the last 7 days is a statistical outlier — more than 2 standard deviations above this facility's own 30-day baseline.</p>
                <?php endif; ?>
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
                        <p class="pn-note">Daily case counts, last 90 days, at <?= htmlspecialchars($facilityName) ?> — keyword-matched from real diagnosis/chief-complaint text.</p>
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
                                    <span class="adm-status-title"><?= htmlspecialchars($r['label']) ?> <span class="pn-severity <?= htmlspecialchars($r['badgeClass']) ?>"><?= htmlspecialchars($r['badge']) ?></span><?php if (!empty($r['anomaly'])): ?><span class="pn-severity unusual" title="More than 2 standard deviations above this pair's own 30-day baseline">Unusual</span><?php endif; ?></span>
                                    <span class="adm-status-desc"><?= htmlspecialchars($r['desc']) ?></span>
                                    <?php if (!empty($r['causes'])): ?><span class="pn-causes-note">Likely contributing factors: <?= htmlspecialchars($r['causes']) ?></span><?php endif; ?>
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
                <div class="an-card psd-card">
                    <div class="an-card-header psd-header">
                        <div class="psd-header-title">
                            <h3>Projected Stock Decline</h3>
                            <p class="pn-note">System-wide stock pace — inventory has no per-facility field in this app's schema.</p>
                        </div>
                        <?php if ($pt2ItemList !== []): ?>
                        <div class="psd-header-controls">
                            <div class="psd-dropdown" id="faPredPSDDropdown">
                                <button type="button" class="psd-dropdown-btn" id="faPredPSDDropdownBtn" aria-haspopup="listbox" aria-expanded="false">
                                    <span id="faPredPSDDropdownLabel">—</span>
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <div class="psd-dropdown-panel" id="faPredPSDDropdownPanel" hidden>
                                    <div class="psd-dropdown-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="faPredPSDDropdownSearch" placeholder="Search medicine or vaccine..." autocomplete="off">
                                    </div>
                                    <div class="opf-seg psd-dropdown-seg" id="faPredPSDDropdownSeg" role="tablist" aria-label="Filter by category">
                                        <button type="button" class="opf-seg-btn active" data-category="all">All</button>
                                        <button type="button" class="opf-seg-btn" data-category="medicine">Medicines</button>
                                        <button type="button" class="opf-seg-btn" data-category="vaccine">Vaccines</button>
                                    </div>
                                    <ul class="psd-dropdown-list" id="faPredPSDDropdownList" role="listbox"></ul>
                                </div>
                            </div>
                            <select class="form-select psd-period-select" id="faPredPSDPeriod" aria-label="Forecast period">
                                <option value="7">7 Days</option>
                                <option value="14">14 Days</option>
                                <option value="30" selected>30 Days</option>
                            </select>
                            <button type="button" class="psd-refresh-btn" id="faPredPSDRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($pt2DefaultDetail === null): ?>
                    <div class="pn-empty"><i class="bi bi-info-circle-fill"></i><p>No medicines or vaccines on record yet.</p></div>
                    <?php else: ?>
                    <div id="faPredPSDSharedNameNote"></div>
                    <div class="psd-summary" id="faPredPSDSummary"></div>
                    <div class="an-forecast-legend" id="faPredPSDLegend">
                        <span><span class="dot dashed" style="color:#D93025;"></span> Projected stock</span>
                        <span><span class="dot dashed" style="color:#E8710A;"></span> Reorder level</span>
                    </div>
                    <div class="an-trend-canvas-box"><canvas id="faPredT2StockProjectionChart"></canvas></div>
                    <div class="psd-recommendation" id="faPredPSDRecommendation"></div>
                    <?php endif; ?>
                </div>
                <div class="an-card opf-card">
                    <div class="an-card-header">
                        <h3>Order/Prescription Frequency</h3>
                        <?php if ($pt2OrderFrequencyBreakdown !== []): ?>
                        <button type="button" class="opf-viewall-btn" data-bs-toggle="modal" data-bs-target="#faPredOPFModal"><i class="bi bi-table"></i> View All Items</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($pt2OrderFrequencyBreakdown === []): ?>
                    <div class="pn-empty"><i class="bi bi-info-circle-fill"></i><p>No medicines or vaccines on record yet.</p></div>
                    <?php else: ?>
                    <p class="pn-note">How often each medicine and vaccine has been ordered or prescribed.</p>
                    <div class="opf-toolbar">
                        <div class="opf-seg" id="faPredOPFCategorySeg" role="tablist" aria-label="Filter by category">
                            <button type="button" class="opf-seg-btn active" data-category="all">All Items</button>
                            <button type="button" class="opf-seg-btn" data-category="medicine">Medicines</button>
                            <button type="button" class="opf-seg-btn" data-category="vaccine">Vaccines</button>
                        </div>
                        <select class="form-select opf-range-select" id="faPredOPFRange" aria-label="Date range">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                    <div class="opf-summary">
                        <div class="opf-stat">
                            <span class="opf-stat-value" id="faPredOPFTotalRequests">0</span>
                            <span class="opf-stat-label">Total Requests</span>
                        </div>
                        <div class="opf-stat">
                            <span class="opf-stat-value" id="faPredOPFItemsRequested">0</span>
                            <span class="opf-stat-label">Items Requested</span>
                        </div>
                    </div>
                    <div class="opf-top5-list" id="faPredOPFTop5List"></div>
                    <p class="opf-top5-empty" id="faPredOPFTop5Empty" style="display:none;">No items match this filter.</p>
                    <?php endif; ?>
                </div>

                <div class="modal fade" id="faPredOPFModal" tabindex="-1" aria-labelledby="faPredOPFModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content opf-modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="faPredOPFModalLabel">Order/Prescription Frequency — All Items</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="opf-modal-toolbar">
                                    <div class="opf-modal-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="faPredOPFModalSearch" placeholder="Search by item name...">
                                    </div>
                                    <div class="opf-seg" id="faPredOPFModalCategorySeg" role="tablist" aria-label="Filter by category">
                                        <button type="button" class="opf-seg-btn active" data-category="all">All</button>
                                        <button type="button" class="opf-seg-btn" data-category="medicine">Medicines</button>
                                        <button type="button" class="opf-seg-btn" data-category="vaccine">Vaccines</button>
                                    </div>
                                    <select class="form-select opf-sort-select" id="faPredOPFModalSort" aria-label="Sort">
                                        <option value="total-desc" selected>Highest to Lowest</option>
                                        <option value="total-asc">Lowest to Highest</option>
                                        <option value="name-asc">Name (A-Z)</option>
                                    </select>
                                </div>
                                <div class="opf-modal-table-wrap">
                                    <table class="opf-modal-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Orders</th>
                                                <th>Dispensed</th>
                                                <th>Prescriptions</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="faPredOPFModalTableBody"></tbody>
                                    </table>
                                    <p class="opf-modal-empty" id="faPredOPFModalEmpty" style="display:none;">No items match your search.</p>
                                </div>
                                <div class="opf-modal-pagination" id="faPredOPFModalPagination"></div>
                            </div>
                        </div>
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
                        <div class="an-trend-canvas-box small"><canvas id="faPredT4BarangayChart"></canvas></div>
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

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>

<!-- Hands the base New-Patients forecast chart's data + the 4
     categories' chart data (window.HCPredictiveCharts.renderAll(),
     assets/js/predictive-analytics.js) to this page's init function
     below — same real numbers admin/facility_analytics.php's own
     Predictive & Prescriptive tab uses for this same facility.
     id="reportDataScript" matches every OTHER Analytics & Reports
     page's own inline data script, so assets/js/ajax-tab-nav.js's
     cross-page tab swap (and assets/js/ajax-report-nav.js, if this
     page ever gains a same-page filter) can swap it the same way. -->
<script id="reportDataScript">
    var forecastTrendData = <?= json_encode($forecastTrend) ?>;
    window.PREDICTIVE_TAB_DATA = <?= json_encode([
        't1ForecastTrend' => $pt1ForecastTrend,
        't1DiseaseTrend' => $pt1DiseaseTrend,
        't2ShortageTrend' => $pt2ShortageTrend,
        't3RequestVolume' => $pt3RequestVolume,
        't4ForecastTrend' => $pt4ForecastTrend,
        't4Barangays' => [
            'labels' => array_column($pt4Barangays, 'barangay'),
            'values' => array_column($pt4Barangays, 'total30'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/predictive-analytics.js') ?>"></script>
<script src="<?= asset_url('/assets/js/order-frequency.js') ?>"></script>
<script src="<?= asset_url('/assets/js/stock-projection.js') ?>"></script>
<!-- Exposed as window.initReportPageCharts() — same name
     assets/js/analytics_reports.js and its 5 siblings each define — so
     assets/js/ajax-tab-nav.js's fixed list of re-init hooks picks this
     page's own chart setup correctly after a cross-page tab swap,
     without needing a separate, page-specific hook name. A
     DOMContentLoaded listener alone would never fire for a copy of
     this script loaded dynamically after such a swap (that event
     already happened once, on whichever report page the user actually
     landed on first via a real navigation). -->
<script data-ajax-rerun>
    window.initReportPageCharts = function () {
        if (typeof Chart === 'undefined') return;
        // Already rendered into this <main>'s canvases (DOMContentLoaded
        // can fire more than once after AJAX tab swaps) — nothing to do.
        const renderedCanvas = document.getElementById('forecastTrendChart');
        if (renderedCanvas && Chart.getChart(renderedCanvas)) return;

        const rootStyle = getComputedStyle(document.documentElement);
        const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
        const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

        // Base "New Patients — Actual vs. Forecast" chart — same config
        // admin/facility_analytics.php's own forecastTrendChart uses
        // (assets/js/facility_analytics.js), reproduced inline here since
        // this page doesn't load that whole file (it assumes several
        // OTHER canvases — patientTrendChart, demographicsChart, etc. —
        // that don't exist on this page).
        const forecastCanvas = document.getElementById('forecastTrendChart');
        if (forecastCanvas && forecastTrendData) {
            new Chart(forecastCanvas, {
                type: 'line',
                data: {
                    labels: forecastTrendData.labels,
                    datasets: [
                        {
                            label: 'Actual', data: forecastTrendData.actual,
                            borderColor: '#1A73E8', backgroundColor: '#1A73E8',
                            borderWidth: 2, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#1A73E8', spanGaps: false,
                        },
                        {
                            label: 'Forecast', data: forecastTrendData.forecast,
                            borderColor: '#7C3AED', backgroundColor: '#7C3AED',
                            borderWidth: 2, borderDash: [6, 4], tension: 0.3, pointRadius: 3, pointBackgroundColor: '#7C3AED', spanGaps: false,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } },
                        x: { grid: { display: false }, ticks: { color: tickColor } },
                    },
                },
            });
        }

        // The 4 categories' charts (6.3.1–6.3.4) — same reusable renderer
        // admin/facility_analytics.php's own Predictive tab uses.
        if (window.HCPredictiveCharts && window.PREDICTIVE_TAB_DATA) {
            window.HCPredictiveCharts.renderAll(window.PREDICTIVE_TAB_DATA, 'faPred');
        }
        if (window.HCOrderFrequency) {
            window.HCOrderFrequency.init('faPred', <?= json_encode($pt2OrderFrequencyBreakdown) ?>, '<?= BASE_URL ?>/api/get-order-frequency.php');
        }
        if (window.HCStockProjection) {
            window.HCStockProjection.init('faPred', {
                items: <?= json_encode($pt2ItemList) ?>,
                defaultDetail: <?= json_encode($pt2DefaultDetail) ?>,
                apiUrl: '<?= BASE_URL ?>/api/get-stock-projection.php',
                inventoryUrl: '<?= BASE_URL ?>/subadmin/medicine_vaccine_inventory.php',
            });
        }
    };

    // Registered once — this script re-runs on every AJAX visit to this tab.
    if (!window.__predictiveInitBound) {
        window.__predictiveInitBound = true;
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('forecastTrendChart')) window.initReportPageCharts();
        });
    }
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>