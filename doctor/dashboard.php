<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Doctor Dashboard (doctor/dashboard.php)
 * ---------------------------------------------------------------------
 * Landing dashboard for the "doctor" role — Firestore role value
 * "doctor" (see assets/js/login.js's routeByRole() and
 * api/register.php's $jobTitleToRole). "Doctor" used to be just a job
 * title that resolved to the same "healthcare_staff" role as
 * medical-staff/dashboard.php; it's its own real role now, so a doctor
 * gets their OWN caseload here instead of medical-staff/dashboard.php's
 * facility-wide numbers — see includes/doctor/doctor_dashboard_firestore.php's
 * header comment for exactly how that scoping works.
 *
 * Reuses the same visual shell (280px dark-red gradient sidebar,
 * card/table/donut atoms, all "adm-" prefixed, assets/css/admin-
 * dashboard.css) as admin/, subadmin/, and medical-staff/ so the brand
 * reads as one system, with this page's OWN "doc-" prefixed styles
 * (assets/css/doctor_dashboard.css) for anything specific to it.
 *
 * Patient Management / Consultations / Notifications / Profile pages
 * for this role aren't built yet — their nav links below are ready for
 * when they are, same bootstrapping pattern medical-staff/dashboard.php
 * originally used.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/doctor_dashboard_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $consultationsTrendRange, $consultationsTrend, $patientsByAgeRange,
// $patientsByAge, $patientsByGenderTotal, $patientsByGender,
// $upcomingFollowUps, $consultationStatusTotal, $consultationStatus,
// $todaysSchedule, $scheduleStatusMeta, $priorityMeta,
// $recentNotifications, $recentlyRegisteredPatients

require_once __DIR__ . '/../includes/doctor/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTrend, $predictiveOverviewTopAlerts
// — this page's own predictive preview card ONLY, kept as its own
// require here rather than chained inside doctor_dashboard_firestore.php
// (same isolation convention includes/subadmin/predictive_overview_firestore.php's
// own header comment explains).

// Sidebar nav — Dashboard is active on this page; the rest aren't built
// yet, same bootstrapping pattern medical-staff/dashboard.php originally used.
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php', 'active' => true],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'docNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
        ],
    ],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_doctor_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
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

<!-- Shared admin/subadmin/medical-staff shell (sidebar, topbar, cards, donuts, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Doctor Dashboard page styles (stat cards, chart cards, schedule table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
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
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($doctorProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($doctorProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($doctorNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'docNavAnalyticsReports') ?>">
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
                <span class="doc-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <p class="adm-page-subtitle">Welcome back, <?= htmlspecialchars($doctorProfile['name']) ?>! Here's what's happening today.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this doctor is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a doctor can't reassign
                     themselves. -->
                <div class="doc-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($doctorProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_doctor_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_doctor_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="doc-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="doc-stat-card">
            <div class="doc-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="doc-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="doc-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if (isset($stat['sub'])): ?>
            <div class="doc-stat-sub <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
            </div>
            <?php elseif (isset($stat['staticSub'])): ?>
            <div class="doc-stat-sub muted"><?= htmlspecialchars($stat['staticSub']) ?></div>
            <?php elseif (isset($stat['link'])): ?>
            <a href="<?= htmlspecialchars($stat['link']['href']) ?>" class="doc-stat-link"><?= htmlspecialchars($stat['link']['label']) ?> <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         This doctor's own caseload only — see
         includes/doctor/predictive_overview_firestore.php's header
         comment. The full breakdown lives on
         analytics_reports.php's own preview + admin/facility_analytics.php's
         "Predictive & Prescriptive" tab for this facility. -->
    <?php
    $predictiveOverviewLinkHref = 'analytics_reports.php';
    $predictiveOverviewLinkLabel = 'View Analytics & Reports';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ CONSULTATIONS TREND + AGE GROUP + GENDER ============ -->
    <div class="doc-row-3">

        <div class="adm-card doc-chart-card" data-dashboard-card="consultationsTrend" data-range-param="trendRange">
            <div class="doc-card-header">
                <h2>My Consultations Trend</h2>
                <?php $range = $consultationsTrendRangeState; $drBtnClass = 'doc-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
            </div>
            <div class="doc-canvas-box">
                <canvas id="consultationsTrendChart"></canvas>
            </div>
            <div class="doc-chart-legend">
                <span><span class="dot" style="background:#D93025;"></span> Consultations</span>
            </div>
        </div>

        <div class="adm-card doc-chart-card" data-dashboard-card="patientsByAge" data-range-param="ageRange">
            <div class="doc-card-header">
                <h2>My Patients by Age Group</h2>
                <?php $range = $patientsByAgeRangeState; $drBtnClass = 'doc-range-btn'; require __DIR__ . '/../includes/shared/dashboard_range_dropdown.php'; ?>
            </div>
            <div class="doc-canvas-box">
                <canvas id="patientsByAgeChart"></canvas>
            </div>
        </div>

        <div class="adm-card doc-chart-card">
            <div class="doc-card-header"><h2>My Patients by Gender</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="patientsByGenderChart"></canvas>
                </div>
            </div>
            <div class="doc-donut-legend">
                <?php foreach ($patientsByGender as $g): ?>
                <div class="doc-legend-row">
                    <span class="doc-legend-dot" style="background:<?= htmlspecialchars($g['color']) ?>;"></span>
                    <span class="doc-legend-label"><?= htmlspecialchars($g['label']) ?></span>
                    <span class="doc-legend-value"><?= number_format($g['value']) ?> (<?= rtrim(rtrim(number_format($g['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($patientsByGender)): ?>
                <p class="adm-table-empty" style="padding:8px 0;">No patients yet.</p>
                <?php endif; ?>
                <div class="doc-legend-total">
                    <span>Total</span>
                    <span><?= number_format($patientsByGenderTotal) ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- ============ FOLLOW-UPS + CONSULTATION STATUS + SCHEDULE + NOTIFICATIONS ============ -->
    <div class="doc-row-4">

        <div class="adm-card doc-chart-card">
            <div class="doc-card-header">
                <h2>Upcoming Follow-up Patients</h2>
            </div>
            <div class="doc-followup-list">
                <?php if (empty($upcomingFollowUps)): ?>
                <p class="adm-table-empty" style="padding:8px 0;">No upcoming follow-ups scheduled.</p>
                <?php endif; ?>
                <?php foreach ($upcomingFollowUps as $f): ?>
                <div class="doc-followup-row">
                    <div class="doc-followup-avatar"><i class="bi bi-person-fill"></i></div>
                    <div class="doc-followup-body">
                        <div class="doc-followup-name"><?= htmlspecialchars($f['name']) ?></div>
                        <div class="doc-followup-meta"><?= htmlspecialchars($f['when']) ?><span class="sep">&bull;</span><?= htmlspecialchars($f['time']) ?></div>
                    </div>
                    <i class="bi bi-calendar3 doc-followup-cal"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card doc-chart-card">
            <div class="doc-card-header"><h2>Consultation Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="consultationStatusChart"></canvas>
                </div>
            </div>
            <div class="doc-donut-legend">
                <?php foreach ($consultationStatus as $s): ?>
                <div class="doc-legend-row">
                    <span class="doc-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="doc-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="doc-legend-value"><?= (int) $s['pct'] ?>% (<?= number_format($s['value']) ?>)</span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($consultationStatus)): ?>
                <p class="adm-table-empty" style="padding:8px 0;">No consultations yet.</p>
                <?php endif; ?>
                <div class="doc-legend-total">
                    <span>Total</span>
                    <span><?= number_format($consultationStatusTotal) ?></span>
                </div>
            </div>
        </div>

        <div class="adm-card doc-chart-card">
            <div class="doc-card-header"><h2>Today's Consultation Schedule</h2></div>
            <div class="adm-table-wrap">
                <table class="doc-schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todaysSchedule)): ?>
                        <tr><td colspan="5" class="adm-table-empty">No consultations scheduled for today.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($todaysSchedule as $row):
                            $status = $scheduleStatusMeta[$row['status']] ?? ['label' => ucfirst($row['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280'];
                            $priority = $priorityMeta[$row['priority']] ?? ['label' => $row['priority'] !== '' ? ucfirst($row['priority']) : '—', 'bg' => '#EEF0F3', 'color' => '#6B7280'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['time']) ?></td>
                            <td><?= htmlspecialchars($row['patient']) ?></td>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><span class="doc-badge" style="background:<?= htmlspecialchars($priority['bg']) ?>; color:<?= htmlspecialchars($priority['color']) ?>;"><?= htmlspecialchars($priority['label']) ?></span></td>
                            <td><span class="doc-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card doc-chart-card">
            <div class="doc-card-header">
                <h2>Recent Notifications</h2>
            </div>
            <div class="doc-notif-list">
                <?php if (empty($recentNotifications)): ?>
                <p class="adm-table-empty" style="padding:8px 0;">No recent notifications.</p>
                <?php endif; ?>
                <?php foreach ($recentNotifications as $n): ?>
                <div class="doc-notif-row">
                    <div class="doc-notif-icon" style="background:<?= htmlspecialchars($n['iconBg']) ?>; color:<?= htmlspecialchars($n['iconColor']) ?>;">
                        <i class="bi <?= htmlspecialchars($n['icon']) ?>"></i>
                    </div>
                    <div class="doc-notif-body">
                        <div class="doc-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="doc-notif-desc"><?= htmlspecialchars($n['desc']) ?></div>
                        <div class="doc-notif-time"><?= htmlspecialchars($n['timeAgo']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ MY RECENTLY REGISTERED PATIENTS ============ -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2>My Recently Registered Patients</h2>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date Registered</th>
                        <th>Reason for Visit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentlyRegisteredPatients)): ?>
                    <tr>
                        <td colspan="5" class="adm-table-empty">No patients registered under your care yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($recentlyRegisteredPatients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= (int) $p['age'] ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars($p['dateRegistered']) ?></td>
                        <td><?= htmlspecialchars($p['initialReasonForVisit']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
    // Hands all chart numbers to assets/js/doctor_dashboard.js without
    // it needing to parse PHP-rendered HTML — same pattern as
    // medical-staff/dashboard.php and the other Chart.js-driven pages.
    window.DOCTOR_DASHBOARD_DATA = <?= json_encode([
        'consultationsTrend' => $consultationsTrend,
        'patientsByAge' => $patientsByAge,
        'patientsByGender' => [
            'labels' => array_column($patientsByGender, 'label'),
            'values' => array_column($patientsByGender, 'value'),
            'colors' => array_column($patientsByGender, 'color'),
        ],
        'consultationStatus' => [
            'labels' => array_column($consultationStatus, 'label'),
            'values' => array_column($consultationStatus, 'value'),
            'colors' => array_column($consultationStatus, 'color'),
        ],
    ]) ?>;
    window.HC_DASHBOARD_CARD_ENDPOINT = '../api/doctor-dashboard-card.php';
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/doctor_dashboard.js') ?>"></script>
<script src="<?= asset_url('/assets/js/dashboard-range-filters.js') ?>"></script>
</body>
</html>
