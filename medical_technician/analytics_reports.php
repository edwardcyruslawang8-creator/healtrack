<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Analytics & Reports (medical_technician/analytics_reports.php)
 * ---------------------------------------------------------------------
 * Top-level Medical Technologist section (its own sidebar nav item) —
 * same shape as medical_technician/lab_xray_consultation.php. Reuses
 * the exact same sidebar/topbar shell as the rest of
 * medical_technician/*.php (280px dark-red gradient sidebar, "adm-"/
 * "mt-" prefixed atoms, assets/css/admin-dashboard.css), plus
 * subadmin/analytics_reports.php's own KPI-card/chart-card/donut/
 * ranked-bar-list CSS patterns ("sad-"/"arp-" prefixed,
 * assets/css/subadmin_dashboard.css + assets/css/analytics_reports.css)
 * so the chart language reads as one system across every role.
 * Analytics & Reports stays active in the sidebar, since this IS that
 * page.
 *
 * CONNECTED to real Firestore data — facility-wide, not per-technician
 * (neither lab_results nor xray_results records who completed a test —
 * see includes/medical_technician/analytics_reports_medtech_firestore.php's
 * header comment for why).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'medical_technician') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical_technician/analytics_reports_medtech_firestore.php';
// $staffProfile, $dashboardDateLabel, $analyticsDateRangeLabel, $kpiCards,
// $trendLabels, $trendCounts, $testsByType, $testsByTypeTotal,
// $referralStatus, $referralStatusTotal, $topLabTests, $topLabTestsMax
// hc_medtech_unread_notification_count() (sidebar/topbar badge) already
// comes from config/constants.php, required above.

require_once __DIR__ . '/../includes/firestore/lab_results.php'; // hc_get_pending_lab_xray_count() for the sidebar badge below

require_once __DIR__ . '/../includes/medical_technician/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTrend, $predictiveOverviewTopAlerts,
// $predictiveOverviewLinkHref — rendered via includes/admin/predictive_overview_card.php below.

// Sidebar nav — same Medical Technologist nav list as
// medical_technician/dashboard.php. Analytics & Reports stays active
// (this IS that page).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',             'label' => 'Dashboard',                       'href' => 'dashboard.php'],
    ['icon' => 'bi-clipboard2-pulse-fill', 'label' => 'Laboratory & X-ray',              'href' => 'laboratory_xray.php'],
    ['icon' => 'bi-inboxes-fill',          'label' => 'Laboratory & X-ray Consultation', 'href' => 'lab_xray_consultation.php', 'badge' => hc_get_pending_lab_xray_count()],
    ['icon' => 'bi-bar-chart-fill',        'label' => 'Analytics & Reports',             'href' => 'analytics_reports.php', 'active' => true],
    ['icon' => 'bi-bell-fill',             'label' => 'Notifications',                   'href' => 'notifications.php', 'badge' => hc_medtech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',             'label' => 'Profile / Settings',              'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics &amp; Reports — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Technologist Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/ranked-bar patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports page styles (KPI cards, ranked list, footer notice) -->
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
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Medical technologist profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="mt-nav-badge"><?= (int) $item['badge'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="adm-nav-bottom">
        <hr class="adm-sidebar-divider" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/api/logout.php" class="adm-nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Log Out</span>
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
            <h1>Analytics &amp; Reports</h1>
            <p class="adm-page-subtitle">Laboratory &amp; X-ray activity — <?= htmlspecialchars($analyticsDateRangeLabel) ?> trend, all-time totals.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_medtech_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_medtech_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($staffProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($staffProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

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
                <span class="arp-kpi-trend up">
                    <i class="bi bi-arrow-up-short"></i> <?= htmlspecialchars($kpi['sub']) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         Facility-wide, not per-technician — see
         includes/medical_technician/predictive_overview_firestore.php's
         header comment. -->
    <?php
    $predictiveOverviewLinkHref = null; // no dedicated full predictive page for this role yet
    $predictiveOverviewTrendLabel = 'Lab & X-ray Volume Forecast (actual vs. next 7 days)';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ TREND + BY TYPE + REFERRAL STATUS ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Tests Completed Over Time</h2></div>
            <div class="sad-canvas-box">
                <canvas id="testsOverTimeChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Completed Tests by Type</h2></div>
            <?php if ($testsByTypeTotal > 0): ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="testsByTypeChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($testsByTypeTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($testsByType as $t): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($t['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($t['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($t['value']) ?> (<?= rtrim(rtrim(number_format($t['value'] / $testsByTypeTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="adm-table-empty">No completed tests yet.</p>
            <?php endif; ?>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Referral Status</h2></div>
            <?php if ($referralStatusTotal > 0): ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="referralStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($referralStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($referralStatus as $rs): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($rs['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($rs['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($rs['value']) ?> (<?= rtrim(rtrim(number_format($rs['value'] / $referralStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="adm-table-empty">No referrals on record yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ TOP LAB TESTS ============ -->
    <div class="adm-card sad-chart-card">
        <div class="sad-card-header"><h2>Top 5 Lab Tests Performed</h2></div>
        <?php if ($topLabTests !== []): ?>
        <div class="arp-ranked-axis-label">Total</div>
        <div class="sad-condition-list arp-ranked-list">
            <?php foreach ($topLabTests as $t): ?>
            <div class="sad-condition-row">
                <span class="sad-condition-label"><?= htmlspecialchars($t['label']) ?></span>
                <div class="sad-condition-bar-track">
                    <div class="sad-condition-bar-fill" style="width:<?= round($t['value'] / $topLabTestsMax * 100) ?>%;"></div>
                </div>
                <span class="sad-condition-value"><?= number_format($t['value']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="adm-table-empty">No completed lab tests yet.</p>
        <?php endif; ?>
    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Facility-wide totals across every technician — the trend chart covers the last 14 days.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    window.ANALYTICS_REPORTS_DATA = <?= json_encode([
        'trend' => [
            'canvasId' => 'testsOverTimeChart',
            'labels' => $trendLabels,
            'values' => $trendCounts,
        ],
        'donuts' => [
            [
                'canvasId' => 'testsByTypeChart',
                'labels' => array_column($testsByType, 'label'),
                'values' => array_column($testsByType, 'value'),
                'colors' => array_column($testsByType, 'color'),
            ],
            [
                'canvasId' => 'referralStatusChart',
                'labels' => array_column($referralStatus, 'label'),
                'values' => array_column($referralStatus, 'value'),
                'colors' => array_column($referralStatus, 'color'),
            ],
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/analytics_reports.js') ?>"></script>
</body>
</html>
