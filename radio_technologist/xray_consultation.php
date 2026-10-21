<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * X-ray Consultation (radio_technologist/xray_consultation.php)
 * ---------------------------------------------------------------------
 * The Radio Technologist's referral INBOX — every patient a doctor has
 * just sent for an X-ray examination (a real pending xray_results doc —
 * see includes/radio_technologist/xray_consultation_firestore.php's
 * header comment). Modeled directly on
 * medical_technician/lab_xray_consultation.php, narrowed to X-ray only.
 *
 * Reuses the exact same visual shell and CSS as that page (280px
 * dark-red gradient sidebar "adm-" atoms, "mt-" stat cards, "lxr-"
 * table/filter atoms from assets/css/medical_technician_lab_xray.css)
 * — this page introduces no new CSS of its own.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in radio technologist may view this page.
if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radio_technologist/xray_consultation_firestore.php';
// $staffProfile, $dashboardDateLabel, $referralsSummary, $searchTerm,
// $dateFrom, $dateTo, $referralRows, $referralsTotal, $referralsPerPage,
// $referralsCurrentPage, $referralsTotalPages, $referralsPaginationWindow,
// $referralsStatsSignature, $referralsTableSignature

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

// Sidebar nav — X-ray Consultation is active on this page.
$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-file-earmark-medical-fill', 'label' => 'X-ray Consultation', 'href' => 'xray_consultation.php', 'active' => true, 'badge' => hc_get_pending_xray_count()],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => 'Analytics & Reports',
        'id' => 'rtNavAnalyticsReports',
        'children' => [
            ['label' => 'Analytics', 'href' => 'analytics_reports.php'],
            ['label' => 'My Reports', 'href' => 'patients_report.php'],
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
<title>X-ray Consultation — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin/subadmin/medical-staff/pharmacist shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Medical Technologist Dashboard styles, reused here for the nav badge + topbar select buttons + stat card row -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<!-- Laboratory & X-ray Processing page styles, reused verbatim (filter toolbar, request table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_lab_xray.css') ?>">
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

    <!-- Radio technologist profile -->
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
    <div class="adm-topbar lxc-topbar">
        <div class="lxc-topbar-title">
            <h1>X-ray Consultation</h1>
            <p class="adm-page-subtitle">Patients sent by doctors for X-ray examination, awaiting processing.</p>
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

    <!-- ============ SUMMARY STAT CARD ============
         Polled independently from the table below (own container, own
         signature) by assets/js/radiotech_xray_consultation.js
         (api/radiotech-xray-queue-status.php). -->
    <div id="xcReferralStats" data-signature="<?= htmlspecialchars($referralsStatsSignature) ?>">
        <?php require __DIR__ . '/../includes/radio_technologist/_xray_referral_stats.php'; ?>
    </div>

    <!-- ============ FILTER TOOLBAR ============ -->
    <div class="adm-card lxr-filter-card">
        <form method="get" class="lxr-filter-row">
            <div class="lxr-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by patient name, ID, or request ID...">
            </div>

            <div class="lxr-daterange">
                <i class="bi bi-calendar3"></i>
                <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date">
                <span class="lxr-dot"></span>
                <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date">
            </div>

            <button type="submit" class="lxr-btn-filter"><i class="bi bi-funnel"></i> Filter</button>
        </form>
    </div>

    <!-- ============ REFERRALS TABLE ============
         Polled by assets/js/radiotech_xray_consultation.js
         (api/radiotech-xray-queue-status.php) so a new referral (or one
         someone else completed) shows up here without a manual reload. -->
    <div id="xcReferralTable" data-signature="<?= htmlspecialchars($referralsTableSignature) ?>">
        <?php require __DIR__ . '/../includes/radio_technologist/_xray_referral_table.php'; ?>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/radiotech_xray_consultation.js') ?>"></script>
</body>
</html>
