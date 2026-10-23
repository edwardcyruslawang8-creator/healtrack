<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Archived Items (pharmacist/archived_items.php)
 * ---------------------------------------------------------------------
 * Reached via pharmacist/medicine_vaccine_inventory.php's "View
 * Archived Items" link — not its own sidebar nav item, same reasoning
 * doctor/laboratory_result_details.php stays off the sidebar: a
 * drill-down from the real section (Medicine & Vaccine Inventory
 * stays "active" in the sidebar here too, not a separate entry).
 *
 * CONNECTED to the real "medicines" Firestore collection — see
 * includes/pharmacist/archived_items_pharmacist_firestore.php's header
 * comment. Archiving (api/archive-medicine.php) sets a real "archived"
 * boolean and otherwise every inventory read path excludes it — this
 * page is the one place that flag is actually surfaced again, with a
 * real Unarchive action (api/unarchive-medicine.php) to clear it. No
 * Delete here — api/delete-medicine.php is subadmin-only now (see its
 * own header comment); a pharmacist can only archive/unarchive, never
 * permanently remove an item.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/archived_items_pharmacist_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $archivedSummary,
// $medicineStatusMeta, $medicineExpiryBadgeMeta,
// $medicines, $medicineCategoryOptions, $searchTerm, $categoryFilter,
// $medicinesTotal, $medicinesPerPage, $medicinesCurrentPage, $medicinesTotalPages, $medicinePaginationWindow,
// $vaccines, $vaccineCategoryOptions, $vaccineSearchTerm, $vaccineCategoryFilter,
// $vaccinesTotal, $vaccinesPerPage, $vaccinesCurrentPage, $vaccinesTotalPages, $vaccinePaginationWindow

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

// Sidebar nav — same Pharmacist nav list as every other pharmacist/*.php
// page. Medicine & Vaccine Inventory stays "active" since this page is
// a drill-down from it, not its own section.
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

if (!function_exists('arc_page_url')) {
    function arc_page_url(array $qs, string $pageParam, int $page): string
    {
        $qs[$pageParam] = $page;
        return 'archived_items.php?' . http_build_query($qs);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Items — HEALTrack</title>

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
            <h1>Archived Items</h1>
            <nav class="mvi-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <a href="medicine_vaccine_inventory.php">Medicine &amp; Vaccine Inventory</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <span class="mvi-breadcrumb-current">Archived Items</span>
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

    <!-- ============ ARCHIVED SUMMARY CARDS ============ -->
    <div class="mvi-summary-row">
        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#EEF0F3; color:#6B7280;"><i class="bi bi-archive-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Total Archived</span>
                <span class="mvi-summary-value"><?= number_format($archivedSummary['totalArchived']) ?></span>
                <span class="mvi-summary-sub">All archived medicines &amp; vaccines</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-capsule"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Archived Medicines</span>
                <span class="mvi-summary-value"><?= number_format($archivedSummary['archivedMedicines']) ?></span>
                <span class="mvi-summary-sub">No longer in the active list</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#F1E8FD; color:#8B5CF6;"><i class="bi bi-droplet-half"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Archived Vaccines</span>
                <span class="mvi-summary-value"><?= number_format($archivedSummary['archivedVaccines']) ?></span>
                <span class="mvi-summary-sub">No longer in the active list</span>
            </div>
        </div>
    </div>

    <!-- ============ MEDICINES / VACCINES TABS ============ -->
    <ul class="nav mvi-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="arc-tab-medicines-btn" data-bs-toggle="tab" data-bs-target="#arc-tab-medicines" type="button" role="tab">Medicines</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="arc-tab-vaccines-btn" data-bs-toggle="tab" data-bs-target="#arc-tab-vaccines" type="button" role="tab">Vaccines</button>
        </li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="arc-tab-medicines" role="tabpanel">

        <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
        <form class="mvi-toolbar" method="get" action="archived_items.php">
            <select class="form-select mvi-filter-select" name="category" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($medicineCategoryOptions as $opt): ?>
                <option <?= $opt === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search medicine name, category..." autocomplete="off">
            </div>
            <button type="submit" class="mvi-btn-add" style="background:var(--adm-card); color:var(--adm-text-muted); border:1px solid var(--adm-border);"><i class="bi bi-search"></i></button>
        </form>

        <!-- ============ ARCHIVED MEDICINES TABLE ============ -->
        <div class="adm-card mvi-table-card">
            <div class="adm-table-wrap">
                <table class="adm-table mvi-table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Stock When Archived</th>
                            <th>Expiry Date</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                        <tr>
                            <td colspan="7" class="adm-table-empty">No archived medicines.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($medicines as $med): $expiryBadge = $medicineExpiryBadgeMeta[$med['expiryStatus']] ?? null; ?>
                        <tr
                            data-arc-id="<?= htmlspecialchars($med['id']) ?>"
                            data-arc-type="medicine"
                            data-arc-name="<?= htmlspecialchars($med['name']) ?>"
                        >
                            <td>
                                <div class="mvi-name-cell">
                                    <span class="mvi-thumb" style="background:<?= htmlspecialchars($med['colorMeta']['bg']) ?>; color:<?= htmlspecialchars($med['colorMeta']['color']) ?>;">
                                        <i class="bi <?= htmlspecialchars($med['icon']) ?>"></i>
                                    </span>
                                    <span class="mvi-strong"><?= htmlspecialchars($med['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($med['category']) ?></td>
                            <td><?= htmlspecialchars($med['unit']) ?></td>
                            <td><?= number_format($med['currentStock']) ?></td>
                            <td class="mvi-expiry-cell <?= $med['expiryStatus'] === 'expired' ? 'mvi-expiry-warning' : '' ?>">
                                <?= htmlspecialchars($med['expiryDate']) ?>
                                <?php if ($expiryBadge): ?>
                                <span class="mvi-badge mvi-expiry-badge" style="background:<?= htmlspecialchars($expiryBadge['bg']) ?>; color:<?= htmlspecialchars($expiryBadge['color']) ?>;"><?= htmlspecialchars($expiryBadge['label']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mvi-updated-cell"><?= htmlspecialchars($med['lastUpdated']) ?></td>
                            <td>
                                <div class="adm-action-btns">
                                    <button type="button" class="mvi-manage-btn arc-unarchive" aria-label="Unarchive"><i class="bi bi-arrow-counterclockwise"></i> Unarchive</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text"><?= $medicinesTotal > 0 ? 'Showing 1 to ' . count($medicines) . ' of ' . $medicinesTotal . ' entries' : 'No entries to show' ?></span>
                <?php $medQs = $_GET; unset($medQs['page']); ?>
                <nav class="adm-pagination" aria-label="Archived medicines pages">
                    <a href="<?= arc_page_url($medQs, 'page', max(1, $medicinesCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php foreach ($medicinePaginationWindow as $p): ?>
                        <?php if ($p === '...'): ?>
                        <span class="mvi-page-ellipsis">...</span>
                        <?php else: ?>
                        <a href="<?= arc_page_url($medQs, 'page', (int) $p) ?>" class="page-btn <?= $p === $medicinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= arc_page_url($medQs, 'page', min($medicinesTotalPages, $medicinesCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                </nav>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="arc-tab-vaccines" role="tabpanel">

        <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
        <form class="mvi-toolbar" method="get" action="archived_items.php">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
            <input type="hidden" name="page" value="<?= (int) $medicinesCurrentPage ?>">
            <select class="form-select mvi-filter-select" name="vcategory" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($vaccineCategoryOptions as $opt): ?>
                <option <?= $opt === $vaccineCategoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="vq" value="<?= htmlspecialchars($vaccineSearchTerm) ?>" placeholder="Search vaccine name, category..." autocomplete="off">
            </div>
            <button type="submit" class="mvi-btn-add" style="background:var(--adm-card); color:var(--adm-text-muted); border:1px solid var(--adm-border);"><i class="bi bi-search"></i></button>
        </form>

        <!-- ============ ARCHIVED VACCINES TABLE ============ -->
        <div class="adm-card mvi-table-card">
            <div class="adm-table-wrap">
                <table class="adm-table mvi-table">
                    <thead>
                        <tr>
                            <th>Vaccine Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Stock When Archived</th>
                            <th>Expiry Date</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vaccines)): ?>
                        <tr>
                            <td colspan="7" class="adm-table-empty">No archived vaccines.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($vaccines as $vac): $expiryBadge = $medicineExpiryBadgeMeta[$vac['expiryStatus']] ?? null; ?>
                        <tr
                            data-arc-id="<?= htmlspecialchars($vac['id']) ?>"
                            data-arc-type="vaccine"
                            data-arc-name="<?= htmlspecialchars($vac['name']) ?>"
                        >
                            <td>
                                <div class="mvi-name-cell">
                                    <span class="mvi-thumb" style="background:<?= htmlspecialchars($vac['colorMeta']['bg']) ?>; color:<?= htmlspecialchars($vac['colorMeta']['color']) ?>;">
                                        <i class="bi <?= htmlspecialchars($vac['icon']) ?>"></i>
                                    </span>
                                    <span class="mvi-strong"><?= htmlspecialchars($vac['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($vac['category']) ?></td>
                            <td><?= htmlspecialchars($vac['unit']) ?></td>
                            <td><?= number_format($vac['currentStock']) ?></td>
                            <td class="mvi-expiry-cell <?= $vac['expiryStatus'] === 'expired' ? 'mvi-expiry-warning' : '' ?>">
                                <?= htmlspecialchars($vac['expiryDate']) ?>
                                <?php if ($expiryBadge): ?>
                                <span class="mvi-badge mvi-expiry-badge" style="background:<?= htmlspecialchars($expiryBadge['bg']) ?>; color:<?= htmlspecialchars($expiryBadge['color']) ?>;"><?= htmlspecialchars($expiryBadge['label']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mvi-updated-cell"><?= htmlspecialchars($vac['lastUpdated']) ?></td>
                            <td>
                                <div class="adm-action-btns">
                                    <button type="button" class="mvi-manage-btn arc-unarchive" aria-label="Unarchive"><i class="bi bi-arrow-counterclockwise"></i> Unarchive</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="adm-table-footer">
                <span class="adm-showing-text"><?= $vaccinesTotal > 0 ? 'Showing 1 to ' . count($vaccines) . ' of ' . $vaccinesTotal . ' entries' : 'No entries to show' ?></span>
                <?php $vacQs = $_GET; unset($vacQs['vpage']); ?>
                <nav class="adm-pagination" aria-label="Archived vaccines pages">
                    <a href="<?= arc_page_url($vacQs, 'vpage', max(1, $vaccinesCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php foreach ($vaccinePaginationWindow as $p): ?>
                        <?php if ($p === '...'): ?>
                        <span class="mvi-page-ellipsis">...</span>
                        <?php else: ?>
                        <a href="<?= arc_page_url($vacQs, 'vpage', (int) $p) ?>" class="page-btn <?= $p === $vaccinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= arc_page_url($vacQs, 'vpage', min($vaccinesTotalPages, $vaccinesCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                </nav>
            </div>
        </div>
    </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/archived-items.js') ?>"></script>
</body>
</html>
