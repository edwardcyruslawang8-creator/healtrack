<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * My Reports (radio_technologist/patients_report.php)
 * ---------------------------------------------------------------------
 * The "My Reports" child under the sidebar's Analytics & Reports
 * dropdown — sibling of analytics_reports.php's "Overview" (the
 * KPI/chart page). A date-filtered, exportable list of X-ray exams this
 * technologist personally completed — see
 * includes/radio_technologist/patients_report_radiotech_firestore.php's
 * header comment and includes/shared/patient_consultation_report.php's
 * for the full per-role design.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radio_technologist/patients_report_radiotech_firestore.php';
// $staffProfile, $dashboardDateLabel, $myReportRange, $myPatientReportRows

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-file-earmark-medical-fill', 'label' => 'X-ray Consultation', 'href' => 'xray_consultation.php', 'badge' => hc_get_pending_xray_count()],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'rtNavAnalyticsReports',
        'active' => true,
        'open' => true,
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php', 'active' => true],
        ],
    ],
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_radiotech_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill',       'label' => 'Audit Logs',         'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Reports — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
</head>
<body class="adm-body">

<button class="adm-mobile-menu-btn" id="admSidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="adm-sidebar-backdrop" id="admSidebarBackdrop"></div>

<aside class="adm-sidebar" id="admSidebar">
    <div class="adm-brand">
        <div class="adm-logo-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="adm-brand-text">
            <h1>HEALTrack</h1>
            <p>Health Information System</p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($staffProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($staffProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

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

<main class="adm-main">

    <div class="adm-topbar">
        <div>
            <h1>My Reports</h1>
            <p class="adm-page-subtitle">Every patient whose X-ray exam you personally completed, filtered by date.</p>
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

    <!-- ============ MY REPORTS ============ -->
    <div class="adm-card arp-report-card">
        <div class="sad-card-header"><h2>My Reports</h2></div>
        <p class="adm-page-subtitle">Every patient whose X-ray exam you personally completed, filtered by date — export or print for your records.</p>

        <form class="arp-report-filter" method="get">
            <label>From <input type="date" name="from" value="<?= htmlspecialchars($myReportRange['from']) ?>"></label>
            <label>To <input type="date" name="to" value="<?= htmlspecialchars($myReportRange['to']) ?>"></label>
            <button type="submit" class="arp-report-filter-btn">Apply</button>
            <a href="patients_report.php" class="arp-report-today-link">Today</a>
            <div class="arp-filter-spacer"></div>
            <a href="../api/export-radiotech-patient-report.php?from=<?= urlencode($myReportRange['from']) ?>&to=<?= urlencode($myReportRange['to']) ?>" class="arp-report-export-btn"><i class="bi bi-download"></i> Export CSV</a>
            <button type="button" class="arp-report-export-btn arp-report-print-btn"><i class="bi bi-printer"></i> Print</button>
        </form>

        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myPatientReportRows)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No exams completed in this date range.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($myPatientReportRows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['patientName']) ?></td>
                        <td><?= htmlspecialchars($r['date']) ?></td>
                        <td><?= htmlspecialchars($r['time']) ?></td>
                        <td><?= htmlspecialchars($r['type']) ?></td>
                        <td><?= htmlspecialchars($r['detail']) ?></td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($myPatientReportRows) ?> patient(s) in this range</span>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/analytics_reports.js') ?>"></script>
</body>
</html>
