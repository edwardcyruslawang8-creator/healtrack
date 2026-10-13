<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Analytics & Reports (subadmin/analytics_reports.php)
 * ---------------------------------------------------------------------
 * Top-level Sub-Admin section (its own sidebar nav item) — same shape
 * as subadmin/medicine_vaccine_inventory.php and
 * subadmin/stock_requests.php. Reuses the exact same sidebar/topbar
 * shell as the rest of subadmin/*.php (280px dark-red gradient
 * sidebar, "adm-" prefixed atoms, assets/css/admin-dashboard.css), and
 * reuses subadmin/dashboard.php's donut/legend/ranked-bar-list CSS
 * patterns ("sad-" prefixed, assets/css/subadmin_dashboard.css) plus
 * admin-dashboard.css's .adm-donut-center overlay (the same one
 * admin/account_monitoring.php uses) for the two doughnut charts'
 * center totals, so the chart language reads as one system across the
 * app. Analytics & Reports stays active in the sidebar, since this IS
 * that page.
 *
 * PARTIALLY connected to real Firestore data (see
 * includes/subadmin/analytics_reports_firestore.php's header comment for exactly
 * which KPI cards/charts are real vs. still placeholder). The date
 * range button, facility dropdown, and "View All" / "View Inventory
 * Report" / "View Patient Report" links aren't wired up. The
 * Consultations Over Time card's Daily/Weekly/Monthly dropdown IS wired
 * up — real links (?consultRange=daily|weekly|monthly) intercepted by
 * assets/js/analytics_reports.js for a smooth in-place chart refresh via
 * api/get-consultation-trend.php, same pattern as admin's Patient Trend
 * card (assets/css/analytics_reports.css's .arp-card-loading class).
 * The Consultations Over Time / Consultations by Type
 * / Inventory Status Summary charts are drawn with Chart.js from data
 * JSON-encoded into the page (see assets/js/analytics_reports.js) —
 * same approach as assets/js/subadmin_dashboard.js.
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

require_once __DIR__ . '/../includes/subadmin/analytics_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $facilityOptions, $analyticsTabs,
// $kpiCards, $consultationsOverTimeLabels, $consultationsOverTimeValues,
// $consultationsByType, $consultationsByTypeTotal, $topMedicinesDispensed,
// $topMedicinesDispensedMax, $inventoryStatusSummary, $inventoryStatusTotal,
// $requestStatusMeta, $recentStockRequests, $topDiagnoses, $topDiagnosesMax

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Analytics & Reports stays active (this IS that page).
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
<title>Analytics &amp; Reports — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/ranked-bar patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports page styles (breadcrumb, tabs, KPI cards, footer notice) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
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
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
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
            <h1><?= htmlspecialchars(t('nav.analytics_reports')) ?></h1>
            <nav class="arp-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <span class="arp-breadcrumb-current">Analytics &amp; Reports</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
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
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
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

    <!-- ============ ANALYTICS TABS ============ -->
    <ul class="nav arp-tabs" role="tablist">
        <?php foreach ($analyticsTabs as $tab): ?>
        <li class="nav-item" role="presentation">
            <a href="<?= htmlspecialchars($tab['href']) ?>" class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" data-ajax-tab>
                <?= htmlspecialchars($tab['label']) ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- ============ KPI SUMMARY CARDS ============ -->
    <div class="arp-kpi-row">
        <?php foreach ($kpiCards as $kpi): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($kpi['iconBg']) ?>; color:<?= htmlspecialchars($kpi['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($kpi['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($kpi['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format($kpi['value']) ?></span>
                <span class="arp-kpi-trend <?= $kpi['trend'] === 'down' ? 'down' : 'up' ?>">
                    <i class="bi <?= $kpi['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($kpi['sub']) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE ANALYTICS PREVIEW ============
         A taste of the real forecasting on the "Predictive & Prescriptive"
         tab (includes/subadmin/predictive_analytics_firestore.php reuses
         the exact same admin computation pipeline for this same facility)
         — full detail (all 4 categories, 6.3.1–6.3.4) lives on that tab;
         these 3 cards just surface the headline numbers here on Overview. -->
    <div class="adm-card-header" style="margin-bottom:14px;">
        <h2 style="font-size:16px; font-weight:700; color:var(--adm-text); margin:0;">Predictive Analytics</h2>
        <a href="predictive_analytics.php" class="adm-card-footer-link" style="margin-top:0;">View Full Predictive &amp; Prescriptive Analytics <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="arp-kpi-row" style="margin-bottom:26px;">
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label">Patient Volume Forecast (Next 7 Days)</span>
                <?php if ($patientFit['hasSignal']): ?>
                <span class="arp-kpi-value"><?= number_format($patientForecastTotal) ?></span>
                <span class="arp-kpi-trend <?= $patientForecastTotal >= $patientPast7Total ? 'up' : 'down' ?>">
                    <i class="bi <?= $patientForecastTotal >= $patientPast7Total ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i> vs <?= number_format($patientPast7Total) ?> past 7 days
                </span>
                <?php else: ?>
                <span class="arp-kpi-value">—</span>
                <span class="arp-kpi-trend up">Not enough history yet</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:#EFE7FB; color:#7C3AED;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label">Consultation Load Forecast (Next 7 Days)</span>
                <?php if ($consultFit['hasSignal']): ?>
                <span class="arp-kpi-value"><?= number_format($consultForecastTotal) ?></span>
                <span class="arp-kpi-trend <?= $consultForecastTotal >= $consultPast7Total ? 'up' : 'down' ?>">
                    <i class="bi <?= $consultForecastTotal >= $consultPast7Total ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i> vs <?= number_format($consultPast7Total) ?> past 7 days
                </span>
                <?php else: ?>
                <span class="arp-kpi-value">—</span>
                <span class="arp-kpi-trend up">Not enough history yet</span>
                <?php endif; ?>
            </div>
        </div>
        <?php $pt1Disease = $pt1PredictionCards[1]; ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($pt1Disease['iconBg']) ?>; color:<?= htmlspecialchars($pt1Disease['iconColor']) ?>;"><i class="bi <?= htmlspecialchars($pt1Disease['icon']) ?>"></i></div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($pt1Disease['label']) ?></span>
                <span class="arp-kpi-value"><?= htmlspecialchars($pt1Disease['value']) ?></span>
                <span class="arp-kpi-trend up"><?= htmlspecialchars($pt1Disease['sub']) ?></span>
            </div>
        </div>
    </div>

    <!-- ============ ROW 1: TREND + BY TYPE + TOP MEDICINES ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card" id="consultationsTrendCard" data-range-endpoint="../api/get-consultation-trend.php" data-range-param="consultRange">
            <div class="sad-card-header">
                <h2>Consultations Over Time</h2>
                <div class="dropdown">
                    <button class="sad-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($consultTrendRangeOptions[$consultTrendRange]) ?></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($consultTrendRangeOptions as $rangeKey => $rangeLabel): ?>
                        <li><a class="dropdown-item range-switch-link <?= $rangeKey === $consultTrendRange ? 'active' : '' ?>" href="?consultRange=<?= $rangeKey ?>" data-range="<?= $rangeKey ?>"><?= htmlspecialchars($rangeLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="sad-canvas-box">
                <canvas id="consultationsOverTimeChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Consultations by Type</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="consultationsByTypeChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($consultationsByTypeTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($consultationsByType as $ct): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($ct['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($ct['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($ct['value']) ?> (<?= rtrim(rtrim(number_format($ct['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Top 5 Medicines Dispensed</h2></div>
            <!-- "Times Dispensed" — a real dispensing-EVENT count per item,
                 not true units (no dispensed-quantity field exists anywhere
                 in this app's schema — see includes/subadmin/analytics_reports_firestore.php's
                 header comment). The fixed 0/200/400/600/800 axis this used
                 to show was tuned to placeholder numbers and no longer
                 matches this facility's real (much smaller) counts, so it's
                 removed rather than left showing a misleading scale — each
                 row already states its own real count. -->
            <div class="arp-ranked-axis-label">Times Dispensed</div>
            <?php if ($topMedicinesDispensed === []): ?>
            <p class="adm-table-empty" style="padding:14px 0;">No medicines dispensed at this facility yet.</p>
            <?php else: ?>
            <div class="sad-condition-list arp-ranked-list">
                <?php foreach ($topMedicinesDispensed as $m): ?>
                <div class="sad-condition-row">
                    <span class="sad-condition-label"><?= htmlspecialchars($m['label']) ?></span>
                    <div class="sad-condition-bar-track">
                        <div class="sad-condition-bar-fill" style="width:<?= round($m['value'] / $topMedicinesDispensedMax * 100) ?>%;"></div>
                    </div>
                    <span class="sad-condition-value"><?= number_format($m['value']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ ROW 2: INVENTORY + RECENT REQUESTS + DIAGNOSES ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Inventory Status Summary</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="inventoryStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($inventoryStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total Items</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($inventoryStatusSummary as $inv): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($inv['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($inv['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($inv['value']) ?> (<?= rtrim(rtrim(number_format($inv['value'] / $inventoryStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="medicine_vaccine_inventory.php" class="arp-report-link"><i class="bi bi-box-seam-fill"></i> View Inventory Report</a>
        </div>

        <div class="adm-card">
            <div class="adm-card-header">
                <h2>Recent Stock Requests</h2>
                <a href="stock_requests.php" class="adm-card-footer-link" style="margin-top:0;">View All</a>
            </div>
            <div class="adm-table-wrap">
                <table class="adm-table" data-sortable="true">
                    <thead>
                        <tr>
                            <th>Requested By</th>
                            <th>Request Date</th>
                            <th>Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentStockRequests as $req): $status = $requestStatusMeta[$req['status']] ?? $requestStatusMeta['pending']; ?>
                        <tr>
                            <td><?= htmlspecialchars($req['pharmacist']) ?> <span class="arp-role-inline">(Pharmacist)</span></td>
                            <td class="arp-datetime"><?= htmlspecialchars($req['requestDate']) ?> <span class="arp-dot"></span> <?= htmlspecialchars($req['requestTime']) ?></td>
                            <td><?= (int) $req['itemsCount'] ?> item<?= $req['itemsCount'] === 1 ? '' : 's' ?></td>
                            <td><span class="arp-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Top 5 Diagnoses</h2></div>
            <div class="arp-ranked-axis-label">Total</div>
            <?php if ($topDiagnoses === []): ?>
            <p class="adm-table-empty" style="padding:14px 0;">No diagnoses on record at this facility yet.</p>
            <?php else: ?>
            <div class="sad-condition-list arp-ranked-list arp-ranked-list-stacked">
                <?php foreach ($topDiagnoses as $d): ?>
                <div class="sad-condition-row">
                    <span class="sad-condition-label"><?= htmlspecialchars($d['label']) ?></span>
                    <div class="sad-condition-bar-track">
                        <div class="sad-condition-bar-fill" style="width:<?= round($d['value'] / $topDiagnosesMax * 100) ?>%;"></div>
                    </div>
                    <span class="sad-condition-value"><?= number_format($d['value']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Reports are based on the selected date range and health center.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script id="reportDataScript">
    // Hands all chart numbers to assets/js/analytics_reports.js without it
    // needing to parse PHP-rendered HTML — same pattern as
    // assets/js/subadmin_dashboard.js. The id is what
    // assets/js/ajax-report-nav.js re-executes after a smooth content
    // swap (mutating a <script>'s text never re-runs it, so it creates a
    // fresh element instead).
    window.ANALYTICS_REPORTS_DATA = <?= json_encode([
        'trend' => [
            'canvasId' => 'consultationsOverTimeChart',
            'labels' => $consultationsOverTimeLabels,
            'values' => $consultationsOverTimeValues,
        ],
        'donuts' => [
            [
                'canvasId' => 'consultationsByTypeChart',
                'labels' => array_column($consultationsByType, 'label'),
                'values' => array_column($consultationsByType, 'value'),
                'colors' => array_column($consultationsByType, 'color'),
            ],
            [
                'canvasId' => 'inventoryStatusChart',
                'labels' => array_column($inventoryStatusSummary, 'label'),
                'values' => array_column($inventoryStatusSummary, 'value'),
                'colors' => array_column($inventoryStatusSummary, 'color'),
            ],
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/analytics_reports.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
