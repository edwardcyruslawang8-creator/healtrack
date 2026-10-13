<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Analytics & Reports (doctor/analytics_reports.php)
 * ---------------------------------------------------------------------
 * Top-level Doctor section (its own sidebar nav item) — same shape as
 * doctor/consultations.php. Reuses the exact same sidebar/topbar shell
 * as the rest of doctor/*.php (280px dark-red gradient sidebar, "adm-"/
 * "doc-" prefixed atoms, assets/css/admin-dashboard.css), plus
 * subadmin/analytics_reports.php's own KPI-card/chart-card/donut/
 * ranked-bar-list CSS patterns ("sad-"/"arp-" prefixed,
 * assets/css/subadmin_dashboard.css + assets/css/analytics_reports.css)
 * so the chart language reads as one system across every role, not
 * just sub-admin's. Analytics & Reports stays active in the sidebar,
 * since this IS that page.
 *
 * CONNECTED to real Firestore data, scoped to the signed-in doctor's
 * OWN consultations only — see
 * includes/doctor/analytics_reports_doctor_firestore.php's header
 * comment. Unlike subadmin's version there's no date-range/facility
 * picker (this doctor has one facility, and the trend chart is a fixed
 * last-14-days window) and no tab strip (those tabs link to
 * sub-admin-only report pages that don't apply here). Also includes a
 * Predictive & Prescriptive Analytics preview card, scoped to this same
 * doctor's own caseload — see
 * includes/doctor/predictive_overview_firestore.php's header comment.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/analytics_reports_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $analyticsDateRangeLabel, $kpiCards,
// $trendLabels, $trendCounts, $consultationsByType, $consultationsByTypeTotal,
// $consultationsByStatus, $consultationsByStatusTotal, $topDiagnoses, $topDiagnosesMax
// hc_doctor_unread_notification_count() (sidebar/topbar badge) already
// comes from config/constants.php, required above.

require_once __DIR__ . '/../includes/doctor/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTrend, $predictiveOverviewTopAlerts
// — this doctor's own caseload only (not facility-wide) — see that
// file's header comment.

// Sidebar nav — same Doctor nav list as doctor/dashboard.php.
// Analytics & Reports stays active (this IS that page).
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',           'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',  'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',       'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php', 'active' => true],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',       'href' => 'notifications.php', 'badge' => hc_doctor_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings',  'href' => 'profile_settings.php'],
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
<!-- Doctor Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/ranked-bar patterns (same visual language as subadmin/analytics_reports.php) -->
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

    <!-- Doctor profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><i class="bi bi-person-circle"></i></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($doctorProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($doctorProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($doctorNavItems as $item): ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="doc-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <p class="adm-page-subtitle">Your own consultation activity — <?= htmlspecialchars($analyticsDateRangeLabel) ?> trend, all-time totals.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($doctorProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_doctor_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_doctor_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><i class="bi bi-person-fill"></i></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars(hc_doctor_display_name($doctorProfile['name'])) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($doctorProfile['role']) ?></span>
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
         This doctor's own caseload only — see
         includes/doctor/predictive_overview_firestore.php's header
         comment. -->
    <?php
    $predictiveOverviewLinkHref = null; // no dedicated full predictive page for this role yet
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ TREND + BY TYPE + BY STATUS ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Consultations Over Time</h2></div>
            <div class="sad-canvas-box">
                <canvas id="consultationsOverTimeChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Consultations by Type</h2></div>
            <?php if ($consultationsByTypeTotal > 0): ?>
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
                    <span class="sad-legend-value"><?= number_format($ct['value']) ?> (<?= rtrim(rtrim(number_format($ct['value'] / $consultationsByTypeTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="adm-table-empty">No consultations yet.</p>
            <?php endif; ?>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Consultations by Status</h2></div>
            <?php if ($consultationsByStatusTotal > 0): ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="consultationsByStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($consultationsByStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($consultationsByStatus as $cs): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($cs['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($cs['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($cs['value']) ?> (<?= rtrim(rtrim(number_format($cs['value'] / $consultationsByStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="adm-table-empty">No consultations yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ TOP DIAGNOSES ============ -->
    <div class="adm-card sad-chart-card">
        <div class="sad-card-header"><h2>Top 5 Diagnoses</h2></div>
        <?php if ($topDiagnoses !== []): ?>
        <div class="arp-ranked-axis-label">Total</div>
        <div class="sad-condition-list arp-ranked-list">
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
        <?php else: ?>
        <p class="adm-table-empty">No completed consultations with a recorded diagnosis yet.</p>
        <?php endif; ?>
    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Scoped to your own consultations only — the trend chart covers the last 14 days.</span>
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
            'canvasId' => 'consultationsOverTimeChart',
            'labels' => $trendLabels,
            'values' => $trendCounts,
        ],
        'donuts' => [
            [
                'canvasId' => 'consultationsByTypeChart',
                'labels' => array_column($consultationsByType, 'label'),
                'values' => array_column($consultationsByType, 'value'),
                'colors' => array_column($consultationsByType, 'color'),
            ],
            [
                'canvasId' => 'consultationsByStatusChart',
                'labels' => array_column($consultationsByStatus, 'label'),
                'values' => array_column($consultationsByStatus, 'value'),
                'colors' => array_column($consultationsByStatus, 'color'),
            ],
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/analytics_reports.js') ?>"></script>
</body>
</html>
