<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Notifications (doctor/notifications.php)
 * ---------------------------------------------------------------------
 * Top-level Doctor section (its own sidebar nav item) — same shape as
 * doctor/consultations.php. Reuses the exact same sidebar/topbar shell
 * as the rest of doctor/*.php (280px dark-red gradient sidebar,
 * "adm-"/"doc-" prefixed atoms, assets/css/admin-dashboard.css),
 * including its existing .adm-action-btn (view/delete) and
 * .adm-pagination/.page-btn atoms verbatim. Modeled on
 * medical-staff/notifications.php; reuses assets/css/notifications.css
 * AND assets/js/notifications.js directly — both are already generic
 * (DOM ids only, no role-specific logic), so this page's markup just
 * needs to reuse the same ids for the JS to work unchanged.
 * Notifications stays active in the sidebar, since this IS that page.
 *
 * CONNECTED — every notification is real (see
 * includes/doctor/notifications_doctor_firestore.php's header comment)
 * and Mark as read genuinely persists via $_SESSION['ntfReadIds'] (+
 * the signed-in doctor's own Firestore doc, surviving logout/login too
 * — see api/mark-notifications-read.php). Each row also shows a real
 * "Unread"/"Read" badge next to its title (not just an unread-only dot
 * — see .ntf-read-badge in assets/css/notifications.css). There's no
 * Delete action any more — Actions is a "View Details" button (styled
 * like subadmin/user_management.php's "Manage User" button — same
 * brand-red outline) plus a real Archive/Unarchive action
 * (api/archive-notifications.php / api/unarchive-notifications.php,
 * the exact mechanism the old Delete action used, repurposed) — an
 * archived notification drops out of every tab except the new
 * "Archived" one. Clicking anywhere on the row still opens the same
 * real Notification Details modal (#ntfDetailsModal) and marks it read
 * either way. There are only three tabs now — All Notifications/
 * Unread/Archived — the old System Alerts/Reminders/Updates category
 * tabs were removed. Pagination beyond page 1 is still not wired up.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in doctor may view this page.
if (($_SESSION['role'] ?? null) !== 'doctor') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/doctor/notifications_doctor_firestore.php';
// $doctorProfile, $dashboardDateLabel, $facilityOptions, $notificationTabs,
// $notificationTypeOptions, $notifications, $notificationsTotal,
// $notificationsPerPage, $notificationsCurrentPage, $notificationsTotalPages

// Sidebar nav — same Doctor nav list as doctor/dashboard.php.
// Notifications stays active (this IS that page).
$doctorNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management', 'href' => 'patient_management.php'],
    ['icon' => 'bi-calendar2-heart-fill','label' => 'Consultations',      'href' => 'consultations.php'],
    ['icon' => 'bi-bar-chart-fill',      'label' => 'Analytics & Reports', 'href' => 'analytics_reports.php'],
    ['icon' => 'bi-bell-fill',           'label' => 'Notifications',      'href' => 'notifications.php', 'badge' => hc_doctor_unread_notification_count(), 'active' => true],
    ['icon' => 'bi-clipboard-data-fill', 'label' => 'Audit Logs', 'href' => 'audit_logs.php'],
    ['icon' => 'bi-gear-fill',           'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, table, pagination, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Doctor Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/doctor_dashboard.css') ?>">
<!-- Notifications page styles (tabs, toolbar, notification cell, modal) — shared verbatim with medical-staff/notifications.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/notifications.css') ?>">
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
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="adm-nav-link <?= !empty($item['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                <span class="doc-nav-badge" id="ntfSidebarBadge"><?= (int) $item['badge'] ?></span>
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
            <h1>Notifications</h1>
            <p class="adm-page-subtitle">View and manage all system notifications and alerts.</p>
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
                    <span class="adm-badge-count" id="ntfBellBadge" style="<?= hc_doctor_unread_notification_count() > 0 ? "" : "display:none;" ?>"><?= hc_doctor_unread_notification_count() ?></span>
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
                <tbody id="ntfTableBody">
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
            <span class="adm-showing-text"><?= $notificationsTotal > 0 ? 'Showing ' . (($notificationsCurrentPage - 1) * $notificationsPerPage + 1) . ' to ' . (($notificationsCurrentPage - 1) * $notificationsPerPage + count($notifications)) . ' of ' . $notificationsTotal . ' notifications' : 'No notifications to show' ?></span>
            <?= hc_render_pagination_nav('Notifications pages', 'page', $notificationsCurrentPage, $notificationsTotalPages) ?>
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
