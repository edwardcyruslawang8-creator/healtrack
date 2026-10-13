<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Stock Requests (subadmin/stock_requests.php)
 * ---------------------------------------------------------------------
 * Top-level Sub-Admin section (its own sidebar nav item), reached
 * directly from the sidebar rather than through the patient profile
 * family — same shape as subadmin/medicine_vaccine_inventory.php.
 * Reuses the exact same sidebar/topbar shell as the rest of
 * subadmin/*.php (280px dark-red gradient sidebar, "adm-" prefixed
 * atoms, assets/css/admin-dashboard.css). Stock Requests stays active
 * in the sidebar, since this IS that page.
 *
 * UNLIKE most subadmin/*.php pages built earlier this session, this one
 * is NOT front-end only: $stockRequests reads LIVE from the real
 * "stock_requests" Firestore collection (see
 * includes/subadmin/stock_requests_firestore.php's header comment). The status/
 * pharmacist filters and search box now really filter the table.
 * Approve/Reject/Release Items are all real (three-dot menu ->
 * assets/js/stock_requests.js -> api/update-stock-request-status.php,
 * each only valid from one specific prior status, hidden/disabled
 * otherwise). "View Items"/"View Request" open a real read-only modal
 * (includes/subadmin/stock_request_view_modal.php) showing the actual
 * item list — its footer's "Print" button (plus the three-dot menu's
 * own "Print Request", which doesn't require opening the modal first)
 * populates a hidden print-only twin of that same data and opens the
 * browser's print dialog via window.print() (no PDF generation, just
 * @media print CSS in assets/css/add_medicine_modal.css). Delete
 * Request is real too (api/delete-stock-request.php, with a confirm()
 * step). Every row's data lives in data-request-* attributes on the
 * <tr> itself. The date range button, Edit Request, and pagination
 * beyond page 1 are still not wired up. The "+ New Stock Request"
 * button was removed (decorative, no write endpoint behind it).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in sub-admin may view this page.
if (($_SESSION['role'] ?? null) !== 'subadmin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/subadmin/stock_requests_firestore.php';
// $adminProfile, $dashboardDateLabel, $facilityOptions, $stockRequestSummary,
// $requestStatusOptions, $requestDateRangeLabel, $pharmacistOptions,
// $requestTypeMeta, $requestStatusMeta, $stockRequests, $stockRequestsTotal,
// $stockRequestsPerPage, $stockRequestsCurrentPage, $stockRequestsTotalPages,
// $stockRequestsPaginationWindow

// Sidebar nav — same Sub-Admin nav list as subadmin/dashboard.php.
// Stock Requests stays active (this IS that page).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php', 'active' => true],
    [
        'icon' => 'bi-bar-chart-fill',
        'label' => t('nav.analytics_reports'),
        'id' => 'admNavAnalyticsReports',
        'children' => [
            ['label' => t('nav.analytics_reports_facility'), 'href' => 'analytics_reports.php'],
            ['label' => t('nav.analytics_reports_chatbot'),  'href' => 'chatbot_reports.php'],
        ],
    ],
    ['icon' => 'bi-people-fill',            'label' => t('nav.user_management'),              'href' => 'user_management.php'],
    ['icon' => 'bi-bell-fill',              'label' => t('nav.notifications'),                'href' => 'notifications.php', 'badge' => subadmin_unread_notification_count()],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_pharmacist'),         'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-file-earmark-lock-fill', 'label' => t('nav.consent_management'),           'href' => 'consent_management.php'],
    ['icon' => 'bi-megaphone-fill',         'label' => t('nav.announcements'),                'href' => 'announcements.php'],
    [
        'icon' => 'bi-gear-fill',
        'label' => t('nav.profile_settings'),
        'id' => 'admNavProfileSettings',
        'children' => [
            ['label' => t('nav.profile_settings'), 'href' => 'profile_settings.php'],
            ['label' => t('nav.homepage_settings'), 'href' => 'homepage_settings.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Requests — HEALTrack</title>

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
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Stock Requests page styles (summary cards, toolbar, table) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/stock_requests.css') ?>">
<!-- View Request modal styles, reused from Medicine & Vaccine Inventory's modals -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
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
            <p><?= htmlspecialchars(t('app.tagline')) ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Sub-admin profile -->
    <div class="adm-profile">
        <div class="adm-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></div>
        <div class="adm-profile-text">
            <div class="adm-profile-name"><?= htmlspecialchars($adminProfile['name']) ?></div>
            <div class="adm-profile-role"><?= htmlspecialchars($adminProfile['role']) ?></div>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Navigation -->
    <ul class="adm-nav">
        <?php foreach ($adminNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="<?= htmlspecialchars($item['id'] ?? 'admNavAuditLogs') ?>">
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
                <span class="sad-nav-badge"><?= (int) $item['badge'] ?></span>
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
            <span><?= htmlspecialchars(t('nav.logout')) ?></span>
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
            <h1><?= htmlspecialchars(t('nav.stock_requests')) ?></h1>
            <nav class="srq-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="srq-breadcrumb-sep">&gt;</span>
                <span class="srq-breadcrumb-current">Stock Requests</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Fixed — display only, not a range picker; nothing
                     downstream reads a selected value yet. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>

                <!-- Fixed — shows only the facility this sub-admin is actually
                     assigned to (their real users/{uid} doc's "facility" field);
                     not a switcher, since a sub-admin can't reassign themselves. -->
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($adminProfile['facility']) ?>
                </div>

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (subadmin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= subadmin_unread_notification_count() ?></span><?php endif; ?>
                </a>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i>Profile / Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= htmlspecialchars(t('nav.logout')) ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ STOCK REQUEST SUMMARY CARDS ============ -->
    <div class="srq-summary-row">
        <div class="adm-card srq-summary-card">
            <div class="srq-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-clipboard2-check-fill"></i></div>
            <div class="srq-summary-text">
                <span class="srq-summary-label">Total Requests</span>
                <span class="srq-summary-value"><?= number_format($stockRequestSummary['totalRequests']) ?></span>
                <span class="srq-summary-sub">All time</span>
            </div>
        </div>

        <div class="adm-card srq-summary-card">
            <div class="srq-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
            <div class="srq-summary-text">
                <span class="srq-summary-label">Pending</span>
                <span class="srq-summary-value"><?= number_format($stockRequestSummary['pending']) ?></span>
                <span class="srq-summary-sub">Awaiting approval</span>
            </div>
        </div>
    </div>

    <!-- ============ FILTER & ACTION TOOLBAR ============ -->
    <form class="srq-toolbar" method="get" action="stock_requests.php">
        <select class="form-select srq-filter-select" name="status" aria-label="Filter by status" onchange="this.form.submit()">
            <?php foreach ($requestStatusOptions as $opt): ?>
            <option <?= $opt === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select srq-filter-select" name="daterange" aria-label="Filter by date" onchange="this.form.submit()">
            <?php foreach ($requestDateRangeOptions as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $key === $requestDateRangeFilter ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select srq-filter-select" name="pharmacist" aria-label="Filter by pharmacist" onchange="this.form.submit()">
            <?php foreach ($pharmacistOptions as $opt): ?>
            <option <?= $opt === $pharmacistFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="srq-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by request ID, pharmacist, or item...">
        </div>
        <button type="submit" class="srq-btn-add"><i class="bi bi-search"></i> Search</button>
    </form>

    <!-- ============ STOCK REQUESTS TABLE ============ -->
    <div class="adm-card srq-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table srq-table">
                <thead>
                    <tr>
                        <th>Requested By</th>
                        <th>Request Date</th>
                        <th>Items Requested</th>
                        <th>Request Type</th>
                        <th>Status</th>
                        <th>Date Needed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockRequests)): ?>
                    <tr>
                        <td colspan="7" class="adm-table-empty">No stock requests yet.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($stockRequests as $req): $type = $requestTypeMeta[$req['type']] ?? $requestTypeMeta['medicine']; $status = $requestStatusMeta[$req['status']] ?? $requestStatusMeta['pending']; ?>
                    <tr
                        data-request-id="<?= htmlspecialchars($req['docId']) ?>"
                        data-request-pharmacist="<?= htmlspecialchars($req['pharmacist']) ?>"
                        data-request-date="<?= htmlspecialchars($req['requestDate']) ?>"
                        data-request-time="<?= htmlspecialchars($req['requestTime']) ?>"
                        data-request-type="<?= htmlspecialchars($type['label']) ?>"
                        data-request-type-raw="<?= htmlspecialchars($req['type']) ?>"
                        data-request-status="<?= htmlspecialchars($status['label']) ?>"
                        data-request-date-needed="<?= htmlspecialchars($req['dateNeeded']) ?>"
                        data-request-date-needed-raw="<?= htmlspecialchars($req['dateNeededRaw']) ?>"
                        data-request-facility="<?= htmlspecialchars($req['facility']) ?>"
                        data-request-items="<?= htmlspecialchars(json_encode($req['items'])) ?>"
                    >
                        <td>
                            <div class="srq-pharmacist-cell">
                                <span class="srq-avatar"><i class="bi bi-person-fill"></i></span>
                                <div class="srq-pharmacist-text">
                                    <span class="srq-pharmacist-name"><?= htmlspecialchars($req['pharmacist']) ?></span>
                                    <span class="srq-pharmacist-role">Pharmacist</span>
                                </div>
                            </div>
                        </td>
                        <td class="srq-datetime"><?= htmlspecialchars($req['requestDate']) ?> <span class="srq-dot"></span> <?= htmlspecialchars($req['requestTime']) ?></td>
                        <td>
                            <div class="srq-items-cell">
                                <span class="srq-strong"><?= (int) $req['itemsCount'] ?> item<?= $req['itemsCount'] === 1 ? '' : 's' ?></span>
                                <a href="#" class="srq-view-items srq-view-request">View Items</a>
                            </div>
                        </td>
                        <td><span class="srq-badge" style="background:<?= htmlspecialchars($type['bg']) ?>; color:<?= htmlspecialchars($type['color']) ?>;"><?= htmlspecialchars($type['label']) ?></span></td>
                        <td><span class="srq-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                        <td><?= htmlspecialchars($req['dateNeeded']) ?></td>
                        <td>
                            <div class="dropdown">
                                <button type="button" class="adm-action-btn srq-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item srq-view-request" href="#"><i class="bi bi-eye me-2"></i>View Request</a></li>
                                    <?php if ($req['status'] === 'pending'): ?>
                                    <li><a class="dropdown-item" href="#" data-request-approve data-request-id="<?= htmlspecialchars($req['docId']) ?>"><i class="bi bi-check-circle me-2"></i>Approve Request</a></li>
                                    <li><a class="dropdown-item" href="#" data-request-reject data-request-id="<?= htmlspecialchars($req['docId']) ?>"><i class="bi bi-x-circle me-2"></i>Reject Request</a></li>
                                    <?php else: ?>
                                    <li><span class="dropdown-item disabled"><i class="bi bi-check-circle me-2"></i>Approve Request</span></li>
                                    <li><span class="dropdown-item disabled"><i class="bi bi-x-circle me-2"></i>Reject Request</span></li>
                                    <?php endif; ?>
                                    <?php if ($req['status'] === 'approved'): ?>
                                    <li><a class="dropdown-item" href="#" data-request-release data-request-id="<?= htmlspecialchars($req['docId']) ?>"><i class="bi bi-box-arrow-up me-2"></i>Release Items</a></li>
                                    <?php else: ?>
                                    <li><span class="dropdown-item disabled"><i class="bi bi-box-arrow-up me-2"></i>Release Items</span></li>
                                    <?php endif; ?>
                                    <?php if ($req['status'] === 'pending'): ?>
                                    <li><a class="dropdown-item srq-edit-request" href="#"><i class="bi bi-pencil me-2"></i>Edit Request</a></li>
                                    <?php else: ?>
                                    <li><span class="dropdown-item disabled" title="Only a pending request can be edited"><i class="bi bi-pencil me-2"></i>Edit Request</span></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item srq-print-request" href="#"><i class="bi bi-printer me-2"></i>Print Request</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger srq-delete-request" href="#"><i class="bi bi-trash me-2"></i>Delete Request</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= $stockRequestsTotal > 0 ? 'Showing ' . (($stockRequestsCurrentPage - 1) * $stockRequestsPerPage + 1) . ' to ' . (($stockRequestsCurrentPage - 1) * $stockRequestsPerPage + count($stockRequests)) . ' of ' . $stockRequestsTotal . ' entries' : 'No entries to show' ?></span>
            <?= hc_render_pagination_nav('Stock requests pages', 'page', $stockRequestsCurrentPage, $stockRequestsTotalPages) ?>
        </div>
    </div>

</main>

<?php require __DIR__ . '/../includes/subadmin/stock_request_view_modal.php'; ?>
<?php require __DIR__ . '/../includes/pharmacist/new_stock_request_modal.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/stock_requests.js') ?>"></script>
<script src="<?= asset_url('/assets/js/new-stock-request.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
