<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Radiologic Technologist Dashboard (radiologic_technologist/dashboard.php)
 * ---------------------------------------------------------------------
 * Landing dashboard for the Radiologic Technologist role (Firestore role
 * value "radio_technologist" — see assets/js/login.js's routeByRole()).
 * Reuses the exact same visual shell (280px dark-red gradient sidebar,
 * card/table/donut atoms, all "adm-" prefixed, assets/css/admin-dashboard.css)
 * as every other role, plus assets/css/medical_technician_dashboard.css
 * directly ("mt-" stat cards/chart cards/topbar buttons) — that
 * stylesheet is already role-agnostic (DOM ids only), so this page
 * reuses it rather than a near-identical copy.
 *
 * Scoped to X-ray only — see
 * includes/radiologic_technologist/radiologic_technologist_dashboard_firestore.php's
 * header comment for why this reads hc_get_all_xray_result_docs()
 * alone, never lab_results (that stays medical_technician's own), and
 * for which cards are genuinely "my own work" (completedByUid-scoped)
 * vs. the shared facility-wide pending queue.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in radiologic technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radiologic_technologist/radiologic_technologist_dashboard_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $examsTrendRange, $examsTrend, $resultStatusTotal, $resultStatus,
// $recentlyOrderedExams, $pendingExamsQueue, $examStatusMeta,
// $pendingResults

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

// Sidebar nav — Dashboard is active on this page.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',          'href' => 'dashboard.php', 'active' => true],
    ['icon' => 'bi-file-earmark-medical-fill', 'label' => 'X-ray Management', 'href' => 'xray_consultation.php', 'badge' => hc_get_pending_xray_count()],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'rtNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
    ['icon' => 'bi-bell-fill',              'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_radiotech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill',    'label' => 'Audit Logs',         'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',              'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
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

<!-- Shared admin shell (sidebar, topbar, cards, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Technologist Dashboard styles, reused here verbatim — already role-agnostic (DOM ids only) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
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

    <!-- Radiologic technologist profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($staffNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'rtNavAnalyticsReports') ?>">
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
                <span class="mt-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <h1>Dashboard</h1>
            <p class="adm-page-subtitle">Welcome back, <?= htmlspecialchars(explode(' ', $staffProfile['name'])[0]) ?>! Here's your X-ray workload overview.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this technologist is
                     actually assigned to; not a switcher. -->
                <div class="mt-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_radiotech_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_radiotech_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="mt-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="mt-stat-card">
            <div class="mt-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="mt-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="mt-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if (isset($stat['link'])): ?>
            <a href="<?= htmlspecialchars($stat['link']['href']) ?>" class="mt-stat-link"><?= htmlspecialchars($stat['link']['label']) ?> <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ EXAMS TREND + RESULT STATUS ============ -->
    <div class="mt-row-3">

        <div class="adm-card mt-chart-card" data-dashboard-card="examsTrend" data-range-param="trendRange">
            <div class="mt-card-header">
                <h2>My Exams Completed Trend</h2>
                <?php $range = $examsTrendRangeState; $drBtnClass = 'mt-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
            </div>
            <div class="mt-canvas-box">
                <canvas id="examsTrendChart"></canvas>
            </div>
            <div class="mt-chart-legend">
                <span><span class="dot" style="background:#1A73E8;"></span> My Exams Completed</span>
            </div>
        </div>

        <div class="adm-card mt-chart-card">
            <div class="mt-card-header"><h2>Result Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="resultStatusChart"></canvas>
                </div>
            </div>
            <div class="mt-donut-legend">
                <?php foreach ($resultStatus as $s): ?>
                <div class="mt-legend-row">
                    <span class="mt-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="mt-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="mt-legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
                <div class="mt-legend-total">
                    <span>Total</span>
                    <span><?= number_format($resultStatusTotal) ?></span>
                </div>
            </div>
        </div>

        <div class="adm-card mt-chart-card">
            <div class="mt-card-header">
                <h2>Recently Ordered Exams</h2>
                <a href="xray_consultation.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="mt-followup-list">
                <?php if (empty($recentlyOrderedExams)): ?>
                <p class="adm-table-empty">No exams ordered yet.</p>
                <?php endif; ?>
                <?php foreach ($recentlyOrderedExams as $f): ?>
                <div class="mt-followup-row">
                    <div class="mt-followup-avatar"><i class="bi bi-file-earmark-medical-fill"></i></div>
                    <div class="mt-followup-body">
                        <div class="mt-followup-name"><?= htmlspecialchars($f['name']) ?></div>
                        <div class="mt-followup-meta"><?= htmlspecialchars($f['when']) ?><span class="sep">&bull;</span><?= htmlspecialchars($f['time']) ?></div>
                    </div>
                    <i class="bi bi-calendar3 mt-followup-cal"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ PENDING EXAMS + RECENT NOTIFICATIONS ============ -->
    <div class="mt-row-4">

        <div class="adm-card mt-chart-card" style="grid-column: span 2;">
            <div class="mt-card-header"><h2>Pending Exams (Facility-wide)</h2></div>
            <div class="adm-table-wrap">
                <table class="mt-schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Exam</th>
                            <th>Patient</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingExamsQueue)): ?>
                        <tr>
                            <td colspan="4" class="adm-table-empty">No pending exams right now.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($pendingExamsQueue as $row):
                            $status = $examStatusMeta[$row['status']] ?? ['label' => ucfirst($row['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['orderedTime']) ?></td>
                            <td><?= htmlspecialchars($row['examination']) ?></td>
                            <td><?= htmlspecialchars($row['patientName']) ?></td>
                            <td><span class="mt-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card mt-chart-card" style="grid-column: span 2;">
            <div class="mt-card-header">
                <h2>Pending X-ray Results (Facility-wide)</h2>
                <a href="xray_consultation.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="adm-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Patient</th>
                            <th>Ordered By</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingResults)): ?>
                        <tr>
                            <td colspan="4" class="adm-table-empty">No pending results right now.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($pendingResults as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['test']) ?></td>
                            <td><?= htmlspecialchars($r['patient']) ?></td>
                            <td><?= htmlspecialchars($r['orderedBy']) ?></td>
                            <td><?= htmlspecialchars($r['dueDate']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>

<script>
    // Hands all chart numbers to assets/js/radiologic_technologist_dashboard.js
    // without it needing to parse PHP-rendered HTML — same pattern as
    // medical_technician/dashboard.php's own inline script.
    window.RADIO_TECHNOLOGIST_DASHBOARD_DATA = <?= json_encode([
        'examsTrend' => $examsTrend,
        'resultStatus' => [
            'labels' => array_column($resultStatus, 'label'),
            'values' => array_column($resultStatus, 'value'),
            'colors' => array_column($resultStatus, 'color'),
        ],
    ]) ?>;
    window.HC_DASHBOARD_CARD_ENDPOINT = '../api/radiotech-dashboard-card.php';
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/radiologic_technologist_dashboard.js') ?>"></script>
<script src="<?= asset_url('/assets/js/dashboard-range-filters.js') ?>"></script>
</body>
</html>
