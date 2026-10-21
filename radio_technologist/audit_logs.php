<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Audit Logs (radio_technologist/audit_logs.php)
 * ---------------------------------------------------------------------
 * Top-level Radio Technologist section (its own sidebar nav item) —
 * same shape as radio_technologist/notifications.php. Reuses the exact
 * same sidebar/topbar shell as the rest of radio_technologist/*.php
 * plus assets/css/audit_logs.css directly (the same search bar/filter
 * toolbar/action+status badge styles admin/audit_logs.php and
 * subadmin/audit_logs.php already use).
 *
 * UNLIKE those two pages, this one shows ONLY the signed-in
 * technologist's OWN activity — never another technologist's, never
 * the whole facility's. See
 * includes/radio_technologist/audit_logs_radiotech_firestore.php's
 * header comment for why that means no User/Role filter and no User
 * column at all. Modeled directly on medical_technician/audit_logs.php.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

if (($_SESSION['role'] ?? null) !== 'radio_technologist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/radio_technologist/audit_logs_radiotech_firestore.php';
// $staffProfile, $actionTypeMeta, $auditLogs, $auditLogsTotal,
// $auditLogsPerPage, $auditLogsCurrentPage, $auditLogsTotalPages,
// $auditPaginationWindow, $filterGroups,
// $auditSearchTerm, $auditModuleFilter, $auditDateFrom, $auditDateTo,
// resolveActionMeta(), hc_audit_query()

require_once __DIR__ . '/../includes/firestore/xray_results.php'; // hc_get_pending_xray_count() for the sidebar badge below

$staffNavItems = [
    ['icon' => 'bi-grid-fill',                 'label' => 'Dashboard',          'href' => 'dashboard.php'],
    ['icon' => 'bi-file-earmark-medical-fill', 'label' => 'X-ray Consultation', 'href' => 'xray_consultation.php', 'badge' => hc_get_pending_xray_count()],
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
    ['icon' => 'bi-clipboard-data-fill',       'label' => 'Audit Logs',         'href' => 'audit_logs.php', 'active' => true],
    ['icon' => 'bi-gear-fill',                 'label' => 'Profile / Settings', 'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Logs — HEALTrack</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/audit_logs.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
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
            <h1>Audit Logs</h1>
            <p class="adm-page-subtitle">Your own account activity on HEALTrack — not visible to other radio technologists.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
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

    <!-- ============ SEARCH BAR ============ -->
    <form class="aud-search-bar" method="get" action="audit_logs.php">
        <i class="bi bi-search"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($auditSearchTerm) ?>" placeholder="Search by action, module, or details..." autocomplete="off">
        <input type="hidden" name="module" value="<?= htmlspecialchars($auditModuleFilter) ?>">
        <input type="hidden" name="dateRangeFrom" value="<?= htmlspecialchars($auditDateFrom) ?>">
        <input type="hidden" name="dateRangeTo" value="<?= htmlspecialchars($auditDateTo) ?>">
    </form>

    <!-- ============ FILTER TOOLBAR ============ -->
    <div class="aud-filter-toolbar">
        <form class="aud-daterange" method="get" action="audit_logs.php">
            <i class="bi bi-calendar3"></i>
            <label for="auditDateFrom">From</label>
            <input type="date" id="auditDateFrom" name="dateRangeFrom" value="<?= htmlspecialchars($auditDateFrom) ?>" onchange="this.form.submit()">
            <span class="aud-dot"></span>
            <label for="auditDateTo">To</label>
            <input type="date" id="auditDateTo" name="dateRangeTo" value="<?= htmlspecialchars($auditDateTo) ?>" onchange="this.form.submit()">
            <input type="hidden" name="q" value="<?= htmlspecialchars($auditSearchTerm) ?>">
            <input type="hidden" name="module" value="<?= htmlspecialchars($auditModuleFilter) ?>">
        </form>

        <?php foreach ($filterGroups as $fg): ?>
        <div class="dropdown">
            <button class="aud-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi <?= htmlspecialchars($fg['icon']) ?>"></i> <?= htmlspecialchars($fg['label']) ?>
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($fg['options'] as $opt): ?>
                <li><a class="dropdown-item <?= $opt === $fg['label'] ? 'active' : '' ?>" href="?<?= hc_audit_query([$fg['key'] => $opt, 'page' => 1]) ?>"><?= htmlspecialchars($opt) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>

        <a href="audit_logs.php" class="aud-clear-filters-btn">
            Clear Filters
        </a>
    </div>

    <!-- ============ AUDIT LOGS TABLE ============ -->
    <div class="adm-card aud-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table aud-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time <i class="bi bi-arrow-down"></i></th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="5" class="adm-table-empty">No audit log entries yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($auditLogs as $log): $meta = resolveActionMeta($actionTypeMeta, $log['action']); ?>
                    <tr>
                        <td class="aud-datetime"><?= htmlspecialchars($log['datetime']) ?></td>
                        <td>
                            <span class="aud-action-badge" style="background:<?= htmlspecialchars($meta['bg']) ?>; color:<?= htmlspecialchars($meta['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i> <?= htmlspecialchars($meta['label']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['module']) ?></td>
                        <td class="aud-details"><?= htmlspecialchars($log['details']) ?></td>
                        <td><span class="aud-status-badge <?= htmlspecialchars($log['status']) ?>"><?= ucfirst(htmlspecialchars($log['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $auditLogsTotal > 0 ? 'Showing ' . (($auditLogsCurrentPage - 1) * $auditLogsPerPage + 1) . ' to ' . min($auditLogsCurrentPage * $auditLogsPerPage, $auditLogsTotal) . ' of ' . number_format($auditLogsTotal) . ' entries' : 'No entries to show' ?></span>
            <nav class="adm-pagination" aria-label="Audit log pages">
                <a href="?<?= hc_audit_query(['page' => 1]) ?>" class="page-btn <?= $auditLogsCurrentPage <= 1 ? 'disabled' : '' ?>" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
                <a href="?<?= hc_audit_query(['page' => max(1, $auditLogsCurrentPage - 1)]) ?>" class="page-btn <?= $auditLogsCurrentPage <= 1 ? 'disabled' : '' ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                <?php foreach ($auditPaginationWindow as $p): ?>
                    <?php if ($p === '...'): ?>
                    <span class="page-ellipsis">&hellip;</span>
                    <?php else: ?>
                    <a href="?<?= hc_audit_query(['page' => $p]) ?>" class="page-btn <?= $p === $auditLogsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="?<?= hc_audit_query(['page' => min($auditLogsTotalPages, $auditLogsCurrentPage + 1)]) ?>" class="page-btn <?= $auditLogsCurrentPage >= $auditLogsTotalPages ? 'disabled' : '' ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                <a href="?<?= hc_audit_query(['page' => $auditLogsTotalPages]) ?>" class="page-btn <?= $auditLogsCurrentPage >= $auditLogsTotalPages ? 'disabled' : '' ?>" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
            </nav>
        </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
