<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Medicine & Vaccine Inventory (pharmacist/medicine_vaccine_inventory.php)
 * ---------------------------------------------------------------------
 * Top-level Pharmacist section (its own sidebar nav item), fulfilling
 * the link already present on pharmacist/dashboard.php. Reuses the
 * exact same sidebar/topbar shell as the rest of pharmacist/*.php
 * (280px dark-red gradient sidebar, "adm-"/"phm-" prefixed atoms,
 * assets/css/admin-dashboard.css). Modeled on
 * subadmin/medicine_vaccine_inventory.php; reuses
 * assets/css/medicine_vaccine_inventory.css directly since it's
 * visually identical. Medicine & Vaccine Inventory stays active in the
 * sidebar, since this IS that page.
 *
 * CONNECTED to the real "medicines" Firestore collection — see
 * includes/pharmacist/medicine_vaccine_inventory_pharmacist_firestore.php's
 * header comment. Both tabs (Medicines AND Vaccines) are real, with
 * working search/category/status filters and pagination. "+ Add
 * Medicine"/"+ Add Vaccine" AND each row's Edit (pencil) button open
 * the SAME shared modal (includes/pharmacist/add_medicine_modal.php)
 * — Add writes a real document via api/create-medicine.php, Edit
 * updates the real document via api/update-medicine.php. The
 * three-dot menu's View Details (read-only, client-side only),
 * Adjust Stock (api/adjust-medicine-stock.php), and Delete
 * (api/delete-medicine.php, with a confirm() step) are real too —
 * see includes/pharmacist/medicine_action_modals.php for the first
 * two. Every row's data lives in data-med-* attributes on the <tr>
 * itself now (assets/js/medicine_vaccine_inventory_pharmacist.js reads
 * closest('tr').dataset for all four actions). View Stock History and
 * Archive are still not wired up.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/medicine_vaccine_inventory_pharmacist_firestore.php';
// $staffProfile, $dashboardDateLabel, $facilityOptions, $inventorySummary,
// $medicineStatusMeta, $medicineStatusOptions,
// $medicines, $medicineCategoryOptions, $searchTerm, $categoryFilter, $statusFilterLabel,
// $medicinesTotal, $medicinesPerPage, $medicinesCurrentPage, $medicinesTotalPages, $medicinePaginationWindow,
// $vaccines, $vaccineCategoryOptions, $vaccineSearchTerm, $vaccineCategoryFilter, $vaccineStatusFilterLabel,
// $vaccinesTotal, $vaccinesPerPage, $vaccinesCurrentPage, $vaccinesTotalPages, $vaccinePaginationWindow

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

// Sidebar nav — same Pharmacist nav list as pharmacist/dashboard.php.
// Medicine & Vaccine Inventory stays active (this IS that page).
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

if (!function_exists('mvi_page_url')) {
    function mvi_page_url(array $qs, string $pageParam, int $page): string
    {
        $qs[$pageParam] = $page;
        return 'medicine_vaccine_inventory.php?' . http_build_query($qs);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine &amp; Vaccine Inventory — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Pharmacist Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Medicine & Vaccine Inventory page styles (summary cards, tabs, toolbar, table) — shared verbatim with subadmin/medicine_vaccine_inventory.php -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medicine_vaccine_inventory.css') ?>">
<!-- Quick Search dropdown under the search box (live typeahead, same component as Patient Management's) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/quick-search.css') ?>">
<!-- Add Medicine/Vaccine modal styles -->
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
            <h1>Medicine &amp; Vaccine Inventory</h1>
            <nav class="mvi-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <span class="mvi-breadcrumb-current">Medicine &amp; Vaccine Inventory</span>
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

    <!-- ============ INVENTORY SUMMARY CARDS ============ -->
    <div class="mvi-summary-row">
        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-bag-plus-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Total Items</span>
                <span class="mvi-summary-value"><?= number_format($inventorySummary['totalItems']) ?></span>
                <span class="mvi-summary-sub">All medicine &amp; vaccines</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#E4F7EA; color:#1E8E3E;"><i class="bi bi-check-circle-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">In Stock</span>
                <span class="mvi-summary-value"><?= number_format($inventorySummary['inStock']) ?></span>
                <span class="mvi-summary-sub">Items with sufficient stock</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-exclamation-circle-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Low Stock</span>
                <span class="mvi-summary-value"><?= number_format($inventorySummary['lowStock']) ?></span>
                <span class="mvi-summary-sub">Items at or below minimum level</span>
            </div>
        </div>

        <div class="adm-card mvi-summary-card">
            <div class="mvi-summary-icon" style="background:#FCE4E4; color:#D93025;"><i class="bi bi-x-circle-fill"></i></div>
            <div class="mvi-summary-text">
                <span class="mvi-summary-label">Out of Stock</span>
                <span class="mvi-summary-value"><?= number_format($inventorySummary['outOfStock']) ?></span>
                <span class="mvi-summary-sub">Items out of stock</span>
            </div>
        </div>
    </div>

    <!-- ============ MEDICINES / VACCINES TABS ============ -->
    <ul class="nav mvi-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="mvi-tab-medicines-btn" data-bs-toggle="tab" data-bs-target="#mvi-tab-medicines" type="button" role="tab">Medicines</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="mvi-tab-vaccines-btn" data-bs-toggle="tab" data-bs-target="#mvi-tab-vaccines" type="button" role="tab">Vaccines</button>
        </li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="mvi-tab-medicines" role="tabpanel">

        <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
        <form class="mvi-toolbar" method="get" action="medicine_vaccine_inventory.php">
            <select class="form-select mvi-filter-select" name="category" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($medicineCategoryOptions as $opt): ?>
                <option <?= $opt === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select mvi-filter-select" name="status" aria-label="Filter by status" onchange="this.form.submit()">
                <?php foreach ($medicineStatusOptions as $opt): ?>
                <option <?= $opt === $statusFilterLabel ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search medicine name, category..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-medicines.php?type=medicine">
            </div>
            <button type="submit" class="mvi-btn-add" style="background:var(--adm-card); color:var(--adm-text-muted); border:1px solid var(--adm-border);"><i class="bi bi-search"></i></button>
            <button type="button" class="mvi-btn-add" id="btnAddMedicine"><i class="bi bi-plus-lg"></i> Add Medicine</button>
        </form>

        <!-- ============ MEDICINES TABLE ============ -->
        <div class="adm-card mvi-table-card">
            <div class="adm-table-wrap">
                <table class="adm-table mvi-table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Minimum Level</th>
                            <th>Reorder Level</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                        <tr>
                            <td colspan="10" class="adm-table-empty">No medicines recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($medicines as $med): $status = $medicineStatusMeta[$med['status']] ?? $medicineStatusMeta['available']; ?>
                        <tr
                            data-med-id="<?= htmlspecialchars($med['id']) ?>"
                            data-med-type="medicine"
                            data-med-name="<?= htmlspecialchars($med['name']) ?>"
                            data-med-category="<?= htmlspecialchars($med['category']) ?>"
                            data-med-form="<?= htmlspecialchars($med['form']) ?>"
                            data-med-unit="<?= htmlspecialchars($med['unit']) ?>"
                            data-med-expiry="<?= htmlspecialchars($med['expiryDateRaw']) ?>"
                            data-med-expiry-display="<?= htmlspecialchars($med['expiryDate']) ?>"
                            data-med-stock="<?= (int) $med['currentStock'] ?>"
                            data-med-min-level="<?= (int) $med['minLevel'] ?>"
                            data-med-reorder-level="<?= (int) $med['reorderLevel'] ?>"
                            data-med-desc="<?= htmlspecialchars($med['desc']) ?>"
                            data-med-status-label="<?= htmlspecialchars($status['label']) ?>"
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
                            <td class="<?= $med['currentStock'] === 0 ? 'mvi-zero-stock' : '' ?>"><?= number_format($med['currentStock']) ?></td>
                            <td><?= number_format($med['minLevel']) ?></td>
                            <td><?= number_format($med['reorderLevel']) ?></td>
                            <td class="<?= $med['status'] === 'unavailable' ? 'mvi-expiry-warning' : '' ?>"><?= htmlspecialchars($med['expiryDate']) ?></td>
                            <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                            <td class="mvi-updated-cell"><?= htmlspecialchars($med['lastUpdated']) ?></td>
                            <td>
                                <div class="adm-action-btns">
                                    <button type="button" class="adm-action-btn edit" aria-label="Edit"><i class="bi bi-pencil"></i></button>
                                    <div class="dropdown">
                                        <button type="button" class="adm-action-btn mvi-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item mvi-view-details" href="#"><i class="bi bi-eye me-2"></i>View Details</a></li>
                                            <li><a class="dropdown-item mvi-adjust-stock" href="#"><i class="bi bi-sliders me-2"></i>Adjust Stock</a></li>
                                            <li><a class="dropdown-item mvi-dispense-no-rx" href="#"><i class="bi bi-capsule me-2"></i>Dispense (No Prescription)</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-clock-history me-2"></i>View Stock History</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-archive me-2"></i>Archive</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger mvi-delete" href="#"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
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
                <nav class="adm-pagination" aria-label="Medicines pages">
                    <a href="<?= mvi_page_url($medQs, 'page', max(1, $medicinesCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php foreach ($medicinePaginationWindow as $p): ?>
                        <?php if ($p === '...'): ?>
                        <span class="page-btn mvi-page-ellipsis">&hellip;</span>
                        <?php else: ?>
                        <a href="<?= mvi_page_url($medQs, 'page', $p) ?>" class="page-btn <?= $p === $medicinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= mvi_page_url($medQs, 'page', min($medicinesTotalPages, $medicinesCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                </nav>
            </div>
        </div>

    </div>

    <div class="tab-pane fade" id="mvi-tab-vaccines" role="tabpanel">

        <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
        <form class="mvi-toolbar" method="get" action="medicine_vaccine_inventory.php#vaccines">
            <select class="form-select mvi-filter-select" name="vcategory" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($vaccineCategoryOptions as $opt): ?>
                <option <?= $opt === $vaccineCategoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select mvi-filter-select" name="vstatus" aria-label="Filter by status" onchange="this.form.submit()">
                <?php foreach ($medicineStatusOptions as $opt): ?>
                <option <?= $opt === $vaccineStatusFilterLabel ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="vq" value="<?= htmlspecialchars($vaccineSearchTerm) ?>" placeholder="Search vaccine name, category..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-medicines.php?type=vaccine">
            </div>
            <button type="submit" class="mvi-btn-add" style="background:var(--adm-card); color:var(--adm-text-muted); border:1px solid var(--adm-border);"><i class="bi bi-search"></i></button>
            <button type="button" class="mvi-btn-add" id="btnAddVaccine"><i class="bi bi-plus-lg"></i> Add Vaccine</button>
        </form>

        <!-- ============ VACCINES TABLE ============ -->
        <div class="adm-card mvi-table-card">
            <div class="adm-table-wrap">
                <table class="adm-table mvi-table">
                    <thead>
                        <tr>
                            <th>Vaccine Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Minimum Level</th>
                            <th>Reorder Level</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vaccines)): ?>
                        <tr>
                            <td colspan="10" class="adm-table-empty">No vaccines recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($vaccines as $vac): $status = $medicineStatusMeta[$vac['status']] ?? $medicineStatusMeta['available']; ?>
                        <tr
                            data-med-id="<?= htmlspecialchars($vac['id']) ?>"
                            data-med-type="vaccine"
                            data-med-name="<?= htmlspecialchars($vac['name']) ?>"
                            data-med-category="<?= htmlspecialchars($vac['category']) ?>"
                            data-med-form="<?= htmlspecialchars($vac['form']) ?>"
                            data-med-unit="<?= htmlspecialchars($vac['unit']) ?>"
                            data-med-expiry="<?= htmlspecialchars($vac['expiryDateRaw']) ?>"
                            data-med-expiry-display="<?= htmlspecialchars($vac['expiryDate']) ?>"
                            data-med-stock="<?= (int) $vac['currentStock'] ?>"
                            data-med-min-level="<?= (int) $vac['minLevel'] ?>"
                            data-med-reorder-level="<?= (int) $vac['reorderLevel'] ?>"
                            data-med-desc="<?= htmlspecialchars($vac['desc']) ?>"
                            data-med-status-label="<?= htmlspecialchars($status['label']) ?>"
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
                            <td class="<?= $vac['currentStock'] === 0 ? 'mvi-zero-stock' : '' ?>"><?= number_format($vac['currentStock']) ?></td>
                            <td><?= number_format($vac['minLevel']) ?></td>
                            <td><?= number_format($vac['reorderLevel']) ?></td>
                            <td class="<?= $vac['status'] === 'unavailable' ? 'mvi-expiry-warning' : '' ?>"><?= htmlspecialchars($vac['expiryDate']) ?></td>
                            <td><span class="mvi-badge" style="background:<?= htmlspecialchars($status['bg']) ?>; color:<?= htmlspecialchars($status['color']) ?>;"><?= htmlspecialchars($status['label']) ?></span></td>
                            <td class="mvi-updated-cell"><?= htmlspecialchars($vac['lastUpdated']) ?></td>
                            <td>
                                <div class="adm-action-btns">
                                    <button type="button" class="adm-action-btn edit" aria-label="Edit"><i class="bi bi-pencil"></i></button>
                                    <div class="dropdown">
                                        <button type="button" class="adm-action-btn mvi-more-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item mvi-view-details" href="#"><i class="bi bi-eye me-2"></i>View Details</a></li>
                                            <li><a class="dropdown-item mvi-adjust-stock" href="#"><i class="bi bi-sliders me-2"></i>Adjust Stock</a></li>
                                            <li><a class="dropdown-item mvi-dispense-no-rx" href="#"><i class="bi bi-capsule me-2"></i>Dispense (No Prescription)</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-clock-history me-2"></i>View Stock History</a></li>
                                            <li><a class="dropdown-item" href="#"><i class="bi bi-archive me-2"></i>Archive</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger mvi-delete" href="#"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                        </ul>
                                    </div>
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
                <nav class="adm-pagination" aria-label="Vaccines pages">
                    <a href="<?= mvi_page_url($vacQs, 'vpage', max(1, $vaccinesCurrentPage - 1)) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
                    <?php foreach ($vaccinePaginationWindow as $p): ?>
                        <?php if ($p === '...'): ?>
                        <span class="page-btn mvi-page-ellipsis">&hellip;</span>
                        <?php else: ?>
                        <a href="<?= mvi_page_url($vacQs, 'vpage', $p) ?>" class="page-btn <?= $p === $vaccinesCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <a href="<?= mvi_page_url($vacQs, 'vpage', min($vaccinesTotalPages, $vaccinesCurrentPage + 1)) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
                </nav>
            </div>
        </div>

    </div>
    </div>

</main>

<?php require __DIR__ . '/../includes/pharmacist/add_medicine_modal.php'; ?>
<?php require __DIR__ . '/../includes/pharmacist/medicine_action_modals.php'; ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/quick-search.js') ?>"></script>
<script src="<?= asset_url('/assets/js/medicine_vaccine_inventory_pharmacist.js') ?>"></script>
</body>
</html>
