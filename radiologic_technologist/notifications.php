<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Notifications (radiologic_technologist/notifications.php)
 * ---------------------------------------------------------------------
 * Top-level Radiologic Technologist section (its own sidebar nav item).
 * Reuses the exact same sidebar/topbar shell as the rest of
 * radiologic_technologist/*.php plus assets/css/notifications.css AND
 * assets/js/notifications.js directly — both are already generic (DOM
 * ids only, no role-specific logic). Modeled directly on
 * medical_technician/notifications.php.
 *
 * CONNECTED — every notification is real (see
 * includes/radiologic_technologist/notifications_radiotech_firestore.php's
 * header comment) and Mark as read genuinely persists via
 * $_SESSION['ntfReadIds'] (+ the signed-in technologist's own Firestore
 * doc, surviving logout/login too).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radiologic_technologist/notifications_radiotech_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $notificationTabs,
// $notificationTypeOptions, $notifications, $notificationsTotal,
// $notificationsPerPage, $notificationsUnreadCount, $notificationsArchivedCount

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',          'href' => 'dashboard.php'],
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
    ['icon' => 'bi-bell-fill',                 'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_radiotech_unread_notification_count(), 'active' => true],
    ['icon' => 'bi-clipboard-data-fill',       'label' => 'Audit Logs',         'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/medical_technician_dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/notifications.css') ?>">
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
                <span class="mt-nav-badge" id="ntfSidebarBadge"><?= (int) $item['badge'] ?></span>
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
            <h1>Notifications</h1>
            <p class="adm-page-subtitle">View and manage all system notifications and alerts.</p>
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
                    <span class="adm-badge-count" id="ntfBellBadge" style="<?= hc_radiotech_unread_notification_count() > 0 ? "" : "display:none;" ?>"><?= hc_radiotech_unread_notification_count() ?></span>
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

    <!-- ============ NOTIFICATION CATEGORY TABS ============ -->
    <div class="ntf-tabs-row">
        <ul class="nav ntf-tabs" role="tablist">
            <?php foreach ($notificationTabs as $tab): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= !empty($tab['active']) ? 'active' : '' ?>" type="button" data-ntf-tab="<?= htmlspecialchars($tab['key']) ?>">
                    <?= htmlspecialchars($tab['label']) ?>
                    <?php if (isset($tab['badge'])): ?>
                    <span class="ntf-tab-badge" data-ntf-tab-badge="<?= htmlspecialchars($tab['key']) ?>"<?= $tab['badge'] > 0 ? '' : ' style="display:none"' ?>><?= (int) $tab['badge'] ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="ntf-mark-all-link" id="ntfMarkAllRead"><i class="bi bi-check-lg"></i> Mark all as read</button>
    </div>

    <!-- ============ SEARCH & FILTER TOOLBAR ============ -->
    <div class="ntf-toolbar">
        <div class="ntf-search">
            <i class="bi bi-search"></i>
            <input type="text" id="ntfSearchInput" placeholder="Search notifications...">
        </div>
        <select class="form-select ntf-filter-select" id="ntfTypeFilter" aria-label="Filter by type">
            <?php foreach ($notificationTypeOptions as $opt): ?>
            <option><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- ============ NOTIFICATIONS TABLE ============ -->
    <div class="adm-card ntf-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table ntf-table">
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th>Type</th>
                        <th>Date &amp; Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ntfTableBody" data-initial-unread="<?= (int) $notificationsUnreadCount ?>">
                    <?php if (empty($notifications)): ?>
                    <tr>
                        <td colspan="4" class="adm-table-empty">No notifications yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($notifications as $n): ?>
                    <tr class="ntf-row"
                        data-id="<?= (int) $n['id'] ?>"
                        data-unread="<?= $n['unread'] ? '1' : '0' ?>"
                        data-archived="<?= $n['archived'] ? '1' : '0' ?>"
                        data-type="<?= htmlspecialchars($n['type']) ?>"
                        data-title="<?= htmlspecialchars(strtolower($n['title'])) ?>"
                        data-desc="<?= htmlspecialchars(strtolower($n['description'])) ?>"
                        data-icon="<?= htmlspecialchars($n['icon']) ?>"
                        data-icon-bg="<?= htmlspecialchars($n['iconBg']) ?>"
                        data-icon-color="<?= htmlspecialchars($n['iconColor']) ?>"
                        data-date="<?= htmlspecialchars($n['date']) ?>"
                        data-time="<?= htmlspecialchars($n['time']) ?>">
                        <td>
                            <div class="ntf-notif-cell">
                                <span class="ntf-notif-icon" style="background:<?= htmlspecialchars($n['iconBg']) ?>; color:<?= htmlspecialchars($n['iconColor']) ?>;">
                                    <i class="bi <?= htmlspecialchars($n['icon']) ?>"></i>
                                </span>
                                <div class="ntf-notif-text">
                                    <div class="ntf-notif-title-row">
                                        <span class="ntf-notif-title"><?= htmlspecialchars($n['title']) ?></span>
                                        <span class="ntf-read-badge <?= $n['unread'] ? 'unread' : 'read' ?>"><?= $n['unread'] ? 'Unread' : 'Read' ?></span>
                                    </div>
                                    <span class="ntf-notif-desc"><?= htmlspecialchars($n['description']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($n['type']) ?></td>
                        <td class="ntf-datetime"><?= htmlspecialchars($n['date']) ?> <span class="ntf-dot"></span> <?= htmlspecialchars($n['time']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="ntf-view-details-btn" data-bs-toggle="modal" data-bs-target="#ntfDetailsModal">View Details</button>
                                <?php if ($n['archived']): ?>
                                <button type="button" class="ntf-unarchive-btn">Unarchive</button>
                                <?php else: ?>
                                <button type="button" class="ntf-archive-btn">Archive</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $notificationsTotal > 0 ? 'Showing 1 to ' . min($notificationsPerPage, $notificationsTotal) . ' of ' . $notificationsTotal . ' notifications' : 'No notifications to show' ?></span>
            <!-- Populated by assets/js/notifications.js, not server-rendered
                 — see includes/radiologic_technologist/notifications_radiotech_firestore.php's
                 header comment for why pagination moved client-side. -->
            <nav class="adm-pagination" id="ntfPaginationNav" aria-label="Notifications pages"></nav>
        </div>
    </div>

</main>

<!-- ============ NOTIFICATION DETAILS MODAL ============ -->
<div class="modal fade" id="ntfDetailsModal" tabindex="-1" aria-labelledby="ntfDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ntf-details-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="ntfDetailsModalLabel">Notification Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ntf-details-header">
                    <span class="ntf-notif-icon ntf-notif-icon-lg" id="ntfDetailIcon"><i class="bi bi-bell-fill"></i></span>
                    <div>
                        <div class="ntf-details-title" id="ntfDetailTitle">—</div>
                        <div class="ntf-details-datetime" id="ntfDetailDatetime">—</div>
                    </div>
                </div>
                <p class="ntf-details-desc" id="ntfDetailDesc">—</p>
                <div class="ntf-details-grid">
                    <div class="ntf-detail-field"><span class="ntf-detail-label">Type</span><span class="ntf-detail-value" id="ntfDetailType">—</span></div>
                    <div class="ntf-detail-field"><span class="ntf-detail-label">Status</span><span class="ntf-detail-value" id="ntfDetailStatus">—</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ntf-modal-btn-cancel" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/notifications.js') ?>"></script>
</body>
</html>
