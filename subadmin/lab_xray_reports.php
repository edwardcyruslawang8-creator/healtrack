<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Laboratory & X-ray Reports (subadmin/lab_xray_reports.php)
 * ---------------------------------------------------------------------
 * The "Lab & X-ray Reports" tab of the Analytics & Reports section —
 * a sibling of subadmin/vaccine_reports.php and the other report tabs,
 * reached via their shared report tabs (and vice versa). Reuses the
 * exact same shell as the rest of subadmin/*.php ("adm-" prefixed,
 * assets/css/admin-dashboard.css), the chart-card/legend/ranked-bar
 * patterns from subadmin/dashboard.php ("sad-" prefixed), and the
 * breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer-notice
 * styling from analytics_reports.php ("arp-" prefixed). Page-specific
 * bits live in a small inline <style> block, same convention as
 * vaccine_reports.php.
 *
 * CONNECTED to real Firestore data, scoped to this sub-admin's own
 * facility — see includes/subadmin/lab_xray_reports_firestore.php's
 * header comment. Charts are drawn with Chart.js from data JSON-encoded
 * into the page (see assets/js/lab_xray_reports.js).
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

require_once __DIR__ . '/../includes/subadmin/lab_xray_reports_firestore.php';
// $adminProfile, $analyticsDateRangeLabel, $analyticsTabs, $lxrDateFilter,
// $lxrTypeFilterOptions, $lxrTypeFilterLabel, $lxrStatusFilterOptions,
// $lxrStatusFilterLabel, $labXrayStatCards, $labXrayTrend, $requestsByType,
// $requestsByTypeTotal, $statusByType, $topLabTests, $topLabTestsMax,
// $topXrayExams, $topXrayExamsMax, $topOrderingPhysicians,
// $topOrderingPhysiciansMax, $lxrStatusMeta, $recentLabXrayRequests,
// $recentLabXrayTotal, $recentLabXrayCurrentPage, $recentLabXrayTotalPages,
// $recentLabXrayPerPage

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
<title>Lab &amp; X-ray Reports — HEALTrack</title>

<!-- Poppins (self-hosted — see assets/css/fonts-poppins.css header comment) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/fonts-poppins.css') ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, donuts, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/canvas-box/ranked-bar patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports styles, reused here for the breadcrumb/tabs/section-intro/filter-toolbar/KPI-card/footer notice -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<style>
/* Recent Requests table — 7 columns, so tighter padding/font than
   .adm-table's defaults (same reasoning as vaccine_reports.php's
   .vr-compact-table). */
.lxr-compact-table th,
.lxr-compact-table td{ padding: 10px 8px; font-size: 12px; }
.lxr-compact-table .arp-badge{ padding: 3px 8px; font-size: 10.5px; }
.lxr-type-dot{ display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
.lxr-patient-link{ color: var(--adm-text); font-weight: 600; text-decoration: none; }
.lxr-patient-link:hover{ color: var(--adm-primary, #D93025); text-decoration: underline; }
</style>
</head>
<body class="adm-body">

<!-- Mobile sidebar trigger -->
<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<!-- =============================================================
     SIDEBAR — reused verbatim from the sibling report pages.
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
            <h1><?= htmlspecialchars(t('nav.analytics_reports')) ?></h1>
            <nav class="arp-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <a href="analytics_reports.php">Analytics &amp; Reports</a>
                <span class="arp-breadcrumb-sep">&gt;</span>
                <span class="arp-breadcrumb-current arp-breadcrumb-current-red">Lab &amp; X-ray Reports</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($analyticsDateRangeLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this sub-admin is actually
                     assigned to; not a switcher. -->
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
    <?php require __DIR__ . '/../includes/subadmin/_analytics_tabs.php'; ?>

    <!-- ============ SECTION INTRO ============ -->
    <div class="arp-section-intro">
        <h2>Laboratory &amp; X-ray Reports</h2>
        <p>View laboratory test and X-ray exam requests, completion status, and turnaround at this facility.</p>
    </div>

    <!-- ============ FILTER TOOLBAR ============
         Date range filters on each request's real orderedDate; Type and
         Status filter the WHOLE page. -->
    <div class="arp-filter-bar">
        <?php $drfState = $lxrDateFilter; $drfBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/date_range_filter_dropdown.php'; ?>

        <?php $sfdOptions = $lxrTypeFilterOptions; $sfdParam = 'testType'; $sfdCurrent = $lxrTypeFilterLabel; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <?php $sfdOptions = $lxrStatusFilterOptions; $sfdParam = 'status'; $sfdCurrent = $lxrStatusFilterLabel; $sfdBtnClass = 'arp-filter-select'; include __DIR__ . '/../includes/shared/simple_filter_dropdown.php'; ?>

        <span class="arp-filter-spacer"></span>
    </div>

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="arp-kpi-row">
        <?php foreach ($labXrayStatCards as $stat): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($stat['label']) ?></span>
                <?php if ($stat['value'] === null): ?>
                <span class="arp-kpi-value">—</span>
                <?php else: ?>
                <span class="arp-kpi-value"><?= isset($stat['valueSuffix']) ? rtrim(rtrim(number_format($stat['value'], 1), '0'), '.') . htmlspecialchars($stat['valueSuffix']) : number_format((int) $stat['value']) ?></span>
                <?php endif; ?>
                <?php if (isset($stat['trend'])): ?>
                <span class="arp-kpi-trend <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                    <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
                </span>
                <?php else: ?>
                <span class="arp-kpi-trend"><?= htmlspecialchars($stat['sub']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ TREND + BY TYPE + STATUS BY TYPE ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Requests Trend (Last 14 Days)</h2></div>
            <div class="sad-canvas-box">
                <canvas id="labXrayTrendChart"></canvas>
            </div>
            <div class="sad-chart-legend">
                <span><span class="dot" style="background:#7C3AED;"></span> Laboratory</span>
                <span><span class="dot" style="background:#1A73E8;"></span> X-ray</span>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Requests by Type</h2></div>
            <?php if ($requestsByType === []): ?>
            <p class="adm-table-empty" style="padding:14px 0;">No lab or X-ray requests at this facility yet.</p>
            <?php else: ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="requestsByTypeChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($requestsByTypeTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($requestsByType as $t): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($t['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($t['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($t['value']) ?> (<?= rtrim(rtrim(number_format($t['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Completion Status by Type</h2></div>
            <div class="sad-canvas-box">
                <canvas id="statusByTypeChart"></canvas>
            </div>
            <div class="sad-chart-legend">
                <span><span class="dot" style="background:#1E8E3E;"></span> Completed</span>
                <span><span class="dot" style="background:#F59E0B;"></span> Pending</span>
            </div>
        </div>

    </div>

    <!-- ============ TOP LAB TESTS + TOP X-RAY EXAMS + ORDERING PHYSICIANS ============ -->
    <div class="sad-row-3">
        <?php foreach ([
            ['title' => 'Top 5 Lab Tests Requested',   'rows' => $topLabTests,           'max' => $topLabTestsMax,           'empty' => 'No lab tests requested yet.'],
            ['title' => 'Top 5 X-ray Exams Requested', 'rows' => $topXrayExams,          'max' => $topXrayExamsMax,          'empty' => 'No X-ray exams requested yet.'],
            ['title' => 'Top Ordering Physicians',     'rows' => $topOrderingPhysicians, 'max' => $topOrderingPhysiciansMax, 'empty' => 'No requests on record yet.'],
        ] as $ranked): ?>
        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2><?= htmlspecialchars($ranked['title']) ?></h2></div>
            <div class="arp-ranked-axis-label">Requests</div>
            <?php if ($ranked['rows'] === []): ?>
            <p class="adm-table-empty" style="padding:14px 0;"><?= htmlspecialchars($ranked['empty']) ?></p>
            <?php else: ?>
            <div class="sad-condition-list arp-ranked-list arp-ranked-list-stacked">
                <?php foreach ($ranked['rows'] as $row): ?>
                <div class="sad-condition-row">
                    <span class="sad-condition-label"><?= htmlspecialchars($row['label']) ?></span>
                    <div class="sad-condition-bar-track">
                        <div class="sad-condition-bar-fill" style="width:<?= round($row['value'] / $ranked['max'] * 100) ?>%;"></div>
                    </div>
                    <span class="sad-condition-value"><?= number_format($row['value']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ RECENT REQUESTS ============ -->
    <div class="adm-card">
        <div class="adm-card-header"><h2>Recent Lab &amp; X-ray Requests</h2></div>
        <div class="adm-table-wrap">
            <table class="adm-table lxr-compact-table" style="min-width:0;" data-sortable="true">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Test / Exam</th>
                        <th>Ordered By</th>
                        <th>Date Ordered</th>
                        <th>Date Completed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentLabXrayRequests === []): ?>
                    <tr><td colspan="7" class="adm-table-empty">No lab or X-ray requests match the selected filters.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentLabXrayRequests as $row): $status = $lxrStatusMeta[$row['status']] ?? $lxrStatusMeta['pending']; ?>
                    <tr>
                        <td>
                            <?php if ($row['patientId'] !== ''): ?>
                            <a class="lxr-patient-link" href="patient_laboratory_xray.php?id=<?= urlencode($row['patientId']) ?>"><?= htmlspecialchars($row['patientName']) ?></a>
                            <?php else: ?>
                            <?= htmlspecialchars($row['patientName']) ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="lxr-type-dot" style="background:<?= htmlspecialchars($row['typeColor']) ?>;"></span><?= htmlspecialchars($row['typeLabel']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['orderedBy']) ?></td>
                        <td><?= htmlspecialchars($row['orderedDate']) ?></td>
                        <td><?= htmlspecialchars($row['completedDate']) ?></td>
                        <td><span class="arp-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text">
                <?php if ($recentLabXrayTotal > 0): $shownFrom = ($recentLabXrayCurrentPage - 1) * $recentLabXrayPerPage + 1; $shownTo = $shownFrom + count($recentLabXrayRequests) - 1; ?>
                Showing <?= $shownFrom ?> to <?= $shownTo ?> of <?= number_format($recentLabXrayTotal) ?> entries
                <?php else: ?>
                No entries to show
                <?php endif; ?>
            </span>
            <?= hc_render_pagination_nav('Lab and X-ray request pages', 'lxrPage', $recentLabXrayCurrentPage, $recentLabXrayTotalPages) ?>
        </div>
    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Lab &amp; X-ray reports include every request ordered at this health center within the selected date range.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>

<script id="reportDataScript">
    // Hands all chart numbers to assets/js/lab_xray_reports.js — same
    // pattern as the sibling report pages. The id is what
    // assets/js/ajax-report-nav.js / ajax-tab-nav.js re-execute after a
    // smooth content swap.
    window.LAB_XRAY_REPORTS_DATA = <?= json_encode([
        'trend' => $labXrayTrend,
        'requestsByType' => [
            'labels' => array_column($requestsByType, 'label'),
            'values' => array_column($requestsByType, 'value'),
            'colors' => array_column($requestsByType, 'color'),
        ],
        'statusByType' => $statusByType,
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/sortable-table.js') ?>"></script>
<script src="<?= asset_url('/assets/js/lab_xray_reports.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-report-nav.js') ?>"></script>
<script src="<?= asset_url('/assets/js/ajax-tab-nav.js') ?>"></script>
</body>
</html>
