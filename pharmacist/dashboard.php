<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Pharmacist Dashboard (pharmacist/dashboard.php)
 * ---------------------------------------------------------------------
 * Landing dashboard for the new Pharmacist role (Firestore role value
 * "pharmacist" — see assets/js/login.js's routeByRole()). It reuses
 * the exact same visual shell (280px dark-red gradient sidebar,
 * card/table/donut atoms, all "adm-" prefixed, assets/css/admin-
 * dashboard.css) as admin/, subadmin/, and medical-staff/ so the brand
 * reads as one system, but with its OWN nav list and dashboard content
 * scoped to pharmacy operations (dispensing, medicine stock, stock
 * requests) instead of consultations or facility oversight — built
 * "just like" medical-staff/dashboard.php, meaning the same page
 * structure (5 stat cards, 3-chart row, 4-card row, bottom table), not
 * literally the same content.
 *
 * FRONT-END ONLY for the pharmacy numbers — there's no
 * medicines/dispensing/stock_requests collection scoped to a single
 * pharmacist anywhere in this app yet (see
 * includes/pharmacist/pharmacist_dashboard_firestore.php's header comment). The
 * signed-in pharmacist's own name and assigned facility ARE real, read
 * from their Firestore users/{uid} document.
 *
 * None of this role's other pages (Medicine & Vaccine Inventory, Stock
 * Requests, Notifications, Profile / Settings) are built yet — each
 * sidebar link below is ready for when they are, same as
 * medical-staff/dashboard.php's pattern before patient_management.php
 * existed.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/pharmacist_dashboard_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $statCards,
// $dispensingTrendRange, $dispensingTrend, $medicinesByCategoryRange,
// $medicinesByCategory, $stockStatusTotal, $stockStatus, $expiringSoon,
// $stockRequestStatusTotal, $stockRequestStatus, $todaysStockRequests,
// $stockRequestMeta, $priorityMeta, $recentNotifications,
// $lowStockMedicines

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

require_once __DIR__ . '/../includes/pharmacist/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTrend, $predictiveOverviewTopAlerts
// — this page's own predictive preview card ONLY, kept as its own
// require here rather than chained inside pharmacist_dashboard_firestore.php
// (same isolation convention includes/subadmin/predictive_overview_firestore.php's
// own header comment explains).

// Sidebar nav — Dashboard is active on this page. None of these other
// links are built yet — each is ready for when it is.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',                    'href' => 'dashboard.php', 'active' => true],
    ['icon' => 'bi-capsule',                'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Prescription Queue',           'href' => 'prescription_queue.php', 'badge' => hc_get_pending_pharmacy_order_count()],
    ['icon' => 'bi-box-seam-fill',          'label' => 'Stock Requests',               'href' => 'stock_requests.php'],
    ['icon' => 'bi-bar-chart-fill',        'label' => 'Analytics & Reports',          'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',              'label' => 'Notifications',                'href' => 'notifications.php', 'badge' => hc_pharmacist_unread_notification_count()],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',              'label' => 'Profile / Settings',           'href' => 'profile_settings.php'],
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
<!-- Pharmacist Dashboard page styles (stat cards, chart cards, expiring-soon + notification lists, schedule table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
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

    <!-- Pharmacist profile -->
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
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="phm-nav-badge"><?= (int) $item['badge'] ?></span>
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
                <div class="phm-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this pharmacist is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a pharmacist can't reassign themselves. -->
                <div class="phm-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($staffProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_pharmacist_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_pharmacist_unread_notification_count() ?></span><?php endif; ?>
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
    <div class="phm-stats-row">
        <?php foreach ($statCards as $stat): ?>
        <div class="phm-stat-card">
            <div class="phm-stat-icon" style="background:<?= htmlspecialchars($stat['iconBg']) ?>; color:<?= htmlspecialchars($stat['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($stat['icon']) ?>"></i>
            </div>
            <div class="phm-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
            <div class="phm-stat-value"><?= number_format((int) $stat['value']) ?></div>
            <?php if (isset($stat['sub'])): ?>
            <div class="phm-stat-sub <?= $stat['trend'] === 'down' ? 'down' : 'up' ?>">
                <i class="bi <?= $stat['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($stat['sub']) ?>
            </div>
            <?php elseif (isset($stat['link'])): ?>
            <a href="<?= htmlspecialchars($stat['link']['href']) ?>" class="phm-stat-link"><?= htmlspecialchars($stat['link']['label']) ?> <i class="bi bi-arrow-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         Medicine & Vaccine Shortage + Inventory Replenishment only (not
         disease/consultation forecasting — not this role's job) — see
         includes/pharmacist/predictive_overview_firestore.php's header
         comment. -->
    <?php
    $predictiveOverviewLinkHref = 'analytics_reports.php';
    $predictiveOverviewLinkLabel = 'View Analytics & Reports';
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ DISPENSING TREND + MEDICINES BY CATEGORY + STOCK STATUS ============ -->
    <div class="phm-row-3">

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header">
                <h2>Dispensing Trend</h2>
                <div class="dropdown">
                    <button class="phm-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($dispensingTrendRange) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">This Week</a></li>
                        <li><a class="dropdown-item" href="#">This Month</a></li>
                        <li><a class="dropdown-item" href="#">This Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="phm-canvas-box">
                <canvas id="dispensingTrendChart"></canvas>
            </div>
            <div class="phm-chart-legend">
                <span><span class="dot" style="background:#D93025;"></span> Prescriptions Dispensed</span>
            </div>
        </div>

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header">
                <h2>Medicines by Category</h2>
                <div class="dropdown">
                    <button class="phm-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= htmlspecialchars($medicinesByCategoryRange) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">This Week</a></li>
                        <li><a class="dropdown-item" href="#">This Month</a></li>
                        <li><a class="dropdown-item" href="#">This Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="phm-canvas-box">
                <canvas id="medicinesByCategoryChart"></canvas>
            </div>
        </div>

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header"><h2>Stock Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="stockStatusChart"></canvas>
                </div>
            </div>
            <div class="phm-donut-legend">
                <?php foreach ($stockStatus as $s): ?>
                <div class="phm-legend-row">
                    <span class="phm-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="phm-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="phm-legend-value"><?= number_format($s['value']) ?> (<?= rtrim(rtrim(number_format($s['pct'], 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
                <div class="phm-legend-total">
                    <span>Total</span>
                    <span><?= number_format($stockStatusTotal) ?></span>
                </div>
            </div>
        </div>

    </div>

    <!-- ============ EXPIRING SOON + STOCK REQUEST STATUS + TODAY'S REQUESTS + NOTIFICATIONS ============ -->
    <div class="phm-row-4">

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header">
                <h2>Expiring Soon</h2>
                <a href="medicine_vaccine_inventory.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="phm-followup-list">
                <?php foreach ($expiringSoon as $f): ?>
                <div class="phm-followup-row">
                    <div class="phm-followup-avatar"><i class="bi bi-capsule"></i></div>
                    <div class="phm-followup-body">
                        <div class="phm-followup-name"><?= htmlspecialchars($f['name']) ?></div>
                        <div class="phm-followup-meta"><?= htmlspecialchars($f['when']) ?><span class="sep">&bull;</span><?= htmlspecialchars($f['time']) ?></div>
                    </div>
                    <i class="bi bi-calendar3 phm-followup-cal"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header"><h2>Stock Request Status</h2></div>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="stockRequestStatusChart"></canvas>
                </div>
            </div>
            <div class="phm-donut-legend">
                <?php foreach ($stockRequestStatus as $s): ?>
                <div class="phm-legend-row">
                    <span class="phm-legend-dot" style="background:<?= htmlspecialchars($s['color']) ?>;"></span>
                    <span class="phm-legend-label"><?= htmlspecialchars($s['label']) ?></span>
                    <span class="phm-legend-value"><?= (int) $s['pct'] ?>% (<?= number_format($s['value']) ?>)</span>
                </div>
                <?php endforeach; ?>
                <div class="phm-legend-total">
                    <span>Total</span>
                    <span><?= number_format($stockRequestStatusTotal) ?></span>
                </div>
            </div>
        </div>

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header"><h2>Today's Stock Requests</h2></div>
            <div class="adm-table-wrap">
                <table class="phm-schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Item</th>
                            <th>Requested By</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todaysStockRequests as $row):
                            $status = $stockRequestMeta[$row['status']] ?? ['label' => ucfirst($row['status']), 'bg' => '#EEF0F3', 'color' => '#6B7280'];
                            $priority = $priorityMeta[$row['priority']] ?? ['bg' => '#EEF0F3', 'color' => '#6B7280'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['time']) ?></td>
                            <td><?= htmlspecialchars($row['item']) ?></td>
                            <td><?= htmlspecialchars($row['requestedBy']) ?></td>
                            <td><span class="phm-badge" style="background:<?= htmlspecialchars($priority['bg']) ?>; color:<?= htmlspecialchars($priority['color']) ?>;"><?= htmlspecialchars($row['priority']) ?></span></td>
                            <td><span class="phm-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-card phm-chart-card">
            <div class="phm-card-header">
                <h2>Recent Notifications</h2>
                <a href="notifications.php" class="adm-card-footer-link" style="margin-top:0;">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="phm-notif-list">
                <?php foreach ($recentNotifications as $n): ?>
                <div class="phm-notif-row">
                    <div class="phm-notif-icon" style="background:<?= htmlspecialchars($n['iconBg']) ?>; color:<?= htmlspecialchars($n['iconColor']) ?>;">
                        <i class="bi <?= htmlspecialchars($n['icon']) ?>"></i>
                    </div>
                    <div class="phm-notif-body">
                        <div class="phm-notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="phm-notif-desc"><?= htmlspecialchars($n['desc']) ?></div>
                        <div class="phm-notif-time"><?= htmlspecialchars($n['timeAgo']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============ LOW STOCK MEDICINES ============ -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2>Low Stock Medicines</h2>
            <a href="medicine_vaccine_inventory.php" class="adm-card-footer-link" style="margin-top:0;">View All Medicines <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Medicine ID</th>
                        <th>Medicine Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockMedicines)): ?>
                    <tr>
                        <td colspan="6" class="adm-table-empty">No low stock medicines right now.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($lowStockMedicines as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['id']) ?></td>
                        <td><?= htmlspecialchars($m['name']) ?></td>
                        <td><?= htmlspecialchars($m['category']) ?></td>
                        <td><?= (int) $m['currentStock'] ?></td>
                        <td><?= (int) $m['reorderLevel'] ?></td>
                        <td><?= htmlspecialchars($m['expiryDate']) ?></td>
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
    // Hands all chart numbers to assets/js/pharmacist_dashboard.js without
    // it needing to parse PHP-rendered HTML — same pattern as the other
    // Chart.js-driven admin/subadmin/medical-staff pages.
    window.PHARMACIST_DASHBOARD_DATA = <?= json_encode([
        'dispensingTrend' => $dispensingTrend,
        'medicinesByCategory' => $medicinesByCategory,
        'stockStatus' => [
            'labels' => array_column($stockStatus, 'label'),
            'values' => array_column($stockStatus, 'value'),
            'colors' => array_column($stockStatus, 'color'),
        ],
        'stockRequestStatus' => [
            'labels' => array_column($stockRequestStatus, 'label'),
            'values' => array_column($stockRequestStatus, 'value'),
            'colors' => array_column($stockRequestStatus, 'color'),
        ],
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/pharmacist_dashboard.js') ?>"></script>
</body>
</html>
