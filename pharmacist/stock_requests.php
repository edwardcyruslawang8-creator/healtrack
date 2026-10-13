<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Stock Requests (pharmacist/stock_requests.php)
 * ---------------------------------------------------------------------
 * Top-level Pharmacist section (its own sidebar nav item), fulfilling
 * the link already present on pharmacist/dashboard.php. Reuses the
 * exact same sidebar/topbar shell as the rest of pharmacist/*.php
 * (280px dark-red gradient sidebar, "adm-"/"phm-" prefixed atoms,
 * assets/css/admin-dashboard.css). Modeled on
 * subadmin/stock_requests.php; reuses assets/css/stock_requests.css
 * directly since it's visually identical. Stock Requests stays active
 * in the sidebar, since this IS that page.
 *
 * CONNECTED — $stockRequests reads LIVE from the real "stock_requests"
 * Firestore collection (see
 * includes/pharmacist/stock_requests_pharmacist_firestore.php's header
 * comment), the same collection subadmin/stock_requests.php reads. This
 * page is scoped to ONLY the signed-in pharmacist's own requests
 * (unlike subadmin's page, which sees everyone's) — the "Filter by
 * pharmacist" select is gone along with that broader scope. The status
 * filter and search box really filter the table.
 *
 * This page manages a request's EXISTENCE, not its outcome:
 *   - "+ New Stock Request" (includes/pharmacist/new_stock_request_modal.php
 *     -> assets/js/new-stock-request.js -> api/create-stock-request.php)
 *     creates a real "pending" doc, which appears on
 *     subadmin/stock_requests.php immediately.
 *   - "Edit Request" opens the SAME modal pre-filled, for any of the
 *     pharmacist's own still-pending requests only
 *     (api/update-stock-request.php) — once a sub-admin has acted on
 *     it, it can no longer be edited.
 *   - "Delete Request" (api/delete-stock-request.php) removes a real
 *     doc, ownership-checked server-side.
 *   - "View Request Details" (read-only modal) and "Print Request"
 *     (window.print(), no PDF generation) are real too — same shared
 *     JS/modal as subadmin/stock_requests.php
 *     (assets/js/stock_requests.js,
 *     includes/subadmin/stock_request_view_modal.php, reused as-is).
 * Approve/Reject/Release Items were removed from this page entirely —
 * deciding on a request is a sub-admin capability
 * (api/update-stock-request-status.php is subadmin-only now), not the
 * requesting pharmacist's own. The date range button and pagination
 * beyond page 1 are still not wired up.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/stock_requests_pharmacist_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $stockRequestSummary,
// $requestStatusOptions, $requestDateRangeLabel, $requestTypeMeta,
// $requestStatusMeta, $medicineNames, $vaccineNames, $statusFilter,
// $searchTerm, $stockRequests, $stockRequestsTotal, $stockRequestsPerPage,
// $stockRequestsCurrentPage, $stockRequestsTotalPages

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

// Sidebar nav — same Pharmacist nav list as pharmacist/dashboard.php.
// Stock Requests stays active (this IS that page).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',                    'href' => 'dashboard.php'],
    ['icon' => 'bi-capsule',                'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Prescription Queue',           'href' => 'prescription_queue.php', 'badge' => hc_get_pending_pharmacy_order_count()],
    ['icon' => 'bi-box-seam-fill',          'label' => 'Stock Requests',               'href' => 'stock_requests.php', 'active' => true],
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
<!-- Pharmacist Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Stock Requests page styles (summary cards, toolbar, table) — shared verbatim with subadmin/stock_requests.php -->
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
            <h1>Stock Requests</h1>
            <nav class="srq-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="srq-breadcrumb-sep">&gt;</span>
                <span class="srq-breadcrumb-current">Stock Requests</span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <!-- Static, same as subadmin/dashboard.php's date badge — not a
                     real filter (nothing on this page re-fetches by date), so
                     it's shown as a plain label rather than a dropdown that
                     would silently do nothing when clicked. -->
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
        <div class="srq-search">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by request ID, pharmacist, or item...">
        </div>
        <button type="submit" class="srq-btn-add" style="background:var(--adm-card); color:var(--adm-text-muted); border:1px solid var(--adm-border);"><i class="bi bi-search"></i></button>
        <button type="button" class="srq-btn-add" id="btnNewStockRequest"><i class="bi bi-plus-lg"></i> New Stock Request</button>
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
                        data-request-status-raw="<?= htmlspecialchars($req['status']) ?>"
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
                                <button type="button" class="adm-action-btn srq-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Request actions">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item srq-view-request" href="#"><i class="bi bi-eye me-2"></i>View Request Details</a></li>
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
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
