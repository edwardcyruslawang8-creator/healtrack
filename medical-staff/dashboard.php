<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Medical Staff Dashboard (medical-staff/dashboard.php)
 * ---------------------------------------------------------------------
 * Landing dashboard for the Medical Staff role (Firestore role value
 * "healthcare_staff" — see assets/js/login.js's routeByRole()). It
 * reuses the exact same visual shell (280px dark-red gradient sidebar,
 * card/table/donut atoms, all "adm-" prefixed, assets/css/admin-
 * dashboard.css) as admin/ and subadmin/ so the brand reads as one
 * system, but with its OWN nav list scoped to a single practitioner's
 * day-to-day work (Patient Management, Consultations, ...) instead of
 * facility or sub-admin oversight.
 *
 * FRONT-END ONLY for the clinical numbers — patient/consultation/
 * notification data has no real model anywhere in this app yet (see
 * includes/medical-staff/medical_staff_dashboard_firestore.php's header comment). The
 * signed-in staff member's own name and assigned facility ARE real,
 * read from their Firestore users/{uid} document.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in medical staff member may view this page.
if (($_SESSION['role'] ?? null) !== 'healthcare_staff') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/medical-staff/medical_staff_dashboard_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $consultationsTrendRange, $consultationsTrend, $patientsByAgeRange,
// $patientsByAge, $patientsByGenderTotal, $patientsByGender,
// $upcomingFollowUps, $consultationStatusTotal, $consultationStatus,
// $todaysSchedule, $scheduleStatusMeta, $priorityMeta,
// $recentNotifications, $recentlyRegisteredPatients

require_once __DIR__ . '/../includes/medical-staff/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTopAlerts — this page's
// own predictive preview card ONLY, kept as its own require here rather
// than chained inside medical_staff_dashboard_firestore.php (same
// isolation convention includes/subadmin/predictive_overview_firestore.php's
// own header comment explains).

// Sidebar nav — Dashboard is active on this page. Patient Management
// is built (patient_management.php); the rest of these links aren't
// built yet — each is ready for when it is.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php', 'active' => true],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_medstaff_unread_notification_count()],
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

<!-- Shared admin/subadmin shell (sidebar, topbar, cards, donuts, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Staff Dashboard page styles (stat cards, chart cards, follow-up + notification lists, schedule table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_staff_dashboard.css') ?>">
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

    <!-- Medical staff profile -->
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
                <span class="mst-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <h1>Dashboard</h1>
            <p class="adm-page-subtitle">Welcome back, <?= htmlspecialchars($staffProfile['name']) ?>! Here's what's happening today.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Static, same as subadmin/dashboard.php's date badge — not a
                     real filter (nothing here re-fetches stat cards/charts by
                     date), so it's shown as a plain label rather than a
                     dropdown that would silently do nothing when clicked. -->
                <div class="mst-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this staff member is
                     actually assigned to (their real users/{uid} doc's "facility"
                     field); not a switcher, since a staff member can't reassign
                     themselves. -->
                <div class="mst-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_medstaff_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_medstaff_unread_notification_count() ?></span><?php endif; ?>
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

    <!-- ============ SUMMARY STAT CARDS ============ -->
    <div class="mst-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="mst-stat-card">
            <div class="mst-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="mst-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="mst-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if (isset($stat['sub'])): ?>
            <div class="mst-stat-sub <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
            </div>
            <?php elseif (isset($stat['staticSub'])): ?>
            <div class="mst-stat-sub muted"><?= htmlspecialchars($stat['staticSub']) ?></div>
            <?php elseif (isset($stat['link'])): ?>
            <a href="<?= htmlspecialchars($stat['link']['href']) ?>" class="mst-stat-link"><?= htmlspecialchars($stat['link']['label']) ?> <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         Locked to this staff member's own facility — see
         includes/medical-staff/predictive_overview_firestore.php's
         header comment. The full breakdown lives on
         analytics_reports.php's own preview + admin/facility_analytics.php's
         "Predictive & Prescriptive" tab for this facility. -->
    <?php
    $predictiveOverviewLinkHref = 'analytics_reports.php';
    $predictiveOverviewLinkLabel = 'View Analytics & Reports';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ CONSULTATIONS TREND + AGE GROUP + GENDER ============ -->
    <div class="mst-row-3">

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header">
                <h2>Consultations Trend</h2>
                <div class="dropdown">
                    <button class="mst-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($consultationsTrendRange) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['trendRange' => 'this_week'])) ?>">This Week</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['trendRange' => 'this_month'])) ?>">This Month</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['trendRange' => 'this_year'])) ?>">This Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="mst-canvas-box">
                <canvas id="consultationsTrendChart"></canvas>
            </div>
            <div class="mst-chart-legend">
                <span><span class="dot" style="background:#D93025;"></span> Consultations</span>
            </div>
        </div>

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header">
                <h2>Patients by Age Group</h2>
                <div class="dropdown">
                    <button class="mst-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($patientsByAgeRange) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['ageRange' => 'all'])) ?>">All Time</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['ageRange' => 'today'])) ?>">Today</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['ageRange' => 'this_week'])) ?>">This Week</a></li>
                        <li><a class="dropdown-item" href="<?= htmlspecialchars(hc_url_with_params(['ageRange' => 'this_month'])) ?>">This Month</a></li>
                    </ul>
                </div>
            </div>
            <div class="mst-canvas-box">
                <canvas id="patientsByAgeChart"></canvas>
            </div>
        </div>

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header"><h2>Patients by Gender</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="patientsByGenderChart"></canvas>
                </div>
            </div>
            <div class="mst-donut-legend">
                <?php foreach ($patientsByGender as $g): ?>
                <div class="mst-legend-row">
                    <span class="mst-legend-dot" style="background:<?= htmlspecialchars($g['color']) ?>;"></span>
                    <span class="mst-legend-label"><?= htmlspecialchars($g['label']) ?></span>
                    <span class="mst-legend-value"><?= number_format($g['value']) ?> (<?= rtrim(rtrim(number_format($g['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
                <div class="mst-legend-total">
                    <span>Total</span>
                    <span><?= number_format($patientsByGenderTotal) ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- ============ FOLLOW-UPS + CONSULTATION STATUS + SCHEDULE + NOTIFICATIONS ============ -->
    <div class="mst-row-4">

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header">
                <h2>Upcoming Follow-up Patients</h2>
                <a href="patient_management.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="mst-followup-list">
                <?php if (empty($upcomingFollowUps)): ?>
                <p class="adm-table-empty" style="padding:8px 0;">No upcoming follow-ups scheduled.</p>
                <?php endif; ?>
                <?php foreach ($upcomingFollowUps as $f): ?>
                <div class="mst-followup-row">
                    <div class="mst-followup-avatar"><i class="bi bi-person-fill"></i></div>
                    <div class="mst-followup-body">
                        <div class="mst-followup-name"><?= htmlspecialchars($f['name']) ?></div>
                        <div class="mst-followup-meta"><?= htmlspecialchars($f['when']) ?><span class="sep">&bull;</span><?= htmlspecialchars($f['time']) ?></div>
                    </div>
                    <i class="bi bi-calendar3 mst-followup-cal"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header"><h2>Consultation Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="consultationStatusChart"></canvas>
                </div>
            </div>
            <div class="mst-donut-legend">
                <?php foreach ($consultationStatus as $s): ?>
                <div class="mst-legend-row">
                    <span class="mst-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="mst-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="mst-legend-value"><?= (int) $s['pct'] ?>% (<?= number_format($s['value']) ?>)</span>
                </div>
                <?php endforeach; ?>
                <div class="mst-legend-total">
                    <span>Total</span>
                    <span><?= number_format($consultationStatusTotal) ?></span>
                </div>
            </div>
        </div>

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header"><h2>Today's Consultation Schedule</h2></div>
            <div class="adm-table-wrap">
                <table class="mst-schedule-table">
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
                            <td><span class="mst-badge" style="background:<?= htmlspecialchars($priority['bg']) ?>; color:<?= htmlspecialchars($priority['color']) ?>;"><?= htmlspecialchars($priority['label']) ?></span></td>
                            <td><span class="mst-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card mst-chart-card">
            <div class="mst-card-header">
                <h2>Recent Notifications</h2>
                <a href="notifications.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="mst-notif-list">
                <?php foreach ($recentNotifications as $n): ?>
                <div class="mst-notif-row">
                    <div class="mst-notif-icon" style="background:<?= htmlspecialchars($n['iconBg']) ?>; color:<?= htmlspecialchars($n['iconColor']) ?>;">
                        <i class="bi <?= htmlspecialchars($n['icon']) ?>"></i>
                    </div>
                    <div class="mst-notif-body">
                        <div class="mst-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="mst-notif-desc"><?= htmlspecialchars($n['desc']) ?></div>
                        <div class="mst-notif-time"><?= htmlspecialchars($n['timeAgo']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ RECENTLY REGISTERED PATIENTS ============ -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2>Recently Registered Patients</h2>
            <a href="patient_management.php" class="adm-card-footer-link" style="margin-top:0;">View All Patients <i class="bi bi-arrow-right"></i></a>
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
                        <td colspan="5" class="adm-table-empty">No patients registered yet.</td>
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

<script>
    // Hands all chart numbers to assets/js/medical_staff_dashboard.js without
    // it needing to parse PHP-rendered HTML — same pattern as the other
    // Chart.js-driven admin/subadmin pages.
    window.MEDICAL_STAFF_DASHBOARD_DATA = <?= json_encode([
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
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/medical_staff_dashboard.js') ?>"></script>
</body>
</html>
