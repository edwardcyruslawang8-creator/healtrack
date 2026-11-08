<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Expired Batches (pharmacist/expired_batches.php)
 * ---------------------------------------------------------------------
 * Monitoring-only list of every expired batch archived via
 * api/archive-medicine-batch.php ("Archive Expired Batch" on
 * pharmacist/manage_medicine.php). Reached from the "Expired Batches"
 * button on pharmacist/medicine_vaccine_inventory.php; Medicine &
 * Vaccine Inventory stays active in the sidebar. No unarchive — read
 * only. Data/body shared with subadmin/expired_batches.php
 * (includes/shared/expired_batches_data.php, _expired_batches_content.php).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/shared/expired_batches_data.php';
// $myDoc, $dashboardDateLabel, $ebSummary, $expiredBatches, filters + pagination

$staffProfile = [
    'name'     => $_SESSION['name'] ?? 'Pharmacist',
    'role'     => 'Pharmacist',
    'facility' => $myDoc['facility'] ?? 'North Super Health Center',
];

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

// Sidebar nav — same Pharmacist nav list as every other pharmacist/*.php page.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',                    'href' => 'dashboard.php'],
    ['icon' => 'bi-capsule',                'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php', 'active' => true],
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
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
<title>Expired Batches — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Shared admin shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Pharmacist Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Medicine & Vaccine Inventory page styles — reused verbatim (same summary cards/tabs/toolbar/table look) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medicine_vaccine_inventory.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
</head>
<body class="adm-body mvi-accent-maroon">

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
        <div class="adm-logo-badge"><img src="<?= asset_url('/assets/images/main_logo.png') ?>" alt="HEALTrack logo" class="adm-logo-img"></div>
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
            <h1>Expired Batches</h1>
            <nav class="mvi-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <a href="medicine_vaccine_inventory.php">Medicine &amp; Vaccine Inventory</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <span class="mvi-breadcrumb-current">Expired Batches</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="phm-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>
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

<?php require __DIR__ . '/../includes/shared/_expired_batches_content.php'; ?>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
</body>
</html>
