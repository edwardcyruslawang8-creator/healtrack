<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Medicine & Vaccine Inventory (admin/medicine_vaccine_inventory.php)
 * ---------------------------------------------------------------------
 * Reuses the same sidebar/topbar shell as the rest of admin/*.php, plus
 * the exact same summary-card/tab/toolbar/table markup and CSS
 * subadmin/medicine_vaccine_inventory.php already uses — see
 * includes/admin/medicine_vaccine_inventory_firestore.php's header
 * comment for why the underlying data is guaranteed to be the same real
 * computation (no facility split: this collection isn't tracked per
 * facility at all, unlike patients).
 *
 * Read-only — no Add Medicine/Vaccine button, no Edit/Adjust Stock/
 * Delete actions, and no quick-search typeahead (api/quick-search-medicines.php
 * is role-gated to subadmin/healthcare_staff/doctor/pharmacist only).
 * The full-page Search button/filters below cover the same real data
 * without needing that endpoint widened — same scope line drawn for
 * admin/patient_management.php.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php';          // t()

// Only a signed-in admin (see api/session-login.php) may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/medicine_vaccine_inventory_firestore.php';
// $adminProfile, $dashboardDateLabel, $inventorySummary, $medicineCategoryMeta,
// $medicineCategoryOptions, $medicineStatusOptions, $medicineStatusMeta,
// $medicines, $medicinesTotal, $medicinesPerPage, $medicinesCurrentPage,
// $medicinesTotalPages, $medicinePaginationWindow, $vaccineCategoryMeta,
// $vaccineCategoryOptions, $vaccines, $vaccinesTotal, $vaccinesPerPage,
// $vaccinesCurrentPage, $vaccinesTotalPages, $vaccinePaginationWindow,
// $searchTerm, $categoryFilter, $statusFilterLabel, $vaccineSearchTerm,
// $vaccineCategoryFilter, $vaccineStatusFilterLabel

// Sidebar nav — Medicine & Vaccine Inventory is active on this page.
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php', 'active' => true],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Sub Admin', 'href' => 'audit_logs.php?role=subadmin'],
            ['label' => 'Doctor', 'href' => 'audit_logs.php?role=doctor'],
            ['label' => 'Medical Staff', 'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => 'Medical Technologist', 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => 'Radiologic Technologist', 'href' => 'audit_logs.php?role=radio_technologist'],
            ['label' => 'Pharmacist', 'href' => 'audit_logs.php?role=pharmacist'],
        ],
    ],
    ['icon' => 'bi-gear-fill',           'label' => t('nav.profile_settings'),    'href' => 'profile_settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
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
<!-- Medicine & Vaccine Inventory page styles (summary cards, tabs, toolbar, table) -->
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
            <p><?= t('app.tagline') ?></p>
        </div>
    </div>

    <hr class="adm-sidebar-divider">

    <!-- Admin profile -->
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
            <a href="#admNavAuditLogs" class="adm-nav-link adm-nav-toggle <?= !empty($item['active']) ? 'active' : '' ?> <?= empty($item['open']) ? 'collapsed' : '' ?>" data-bs-toggle="collapse" role="button" aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>" aria-controls="admNavAuditLogs">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse <?= !empty($item['open']) ? 'show' : '' ?>" id="admNavAuditLogs">
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
            </a>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <div class="adm-nav-bottom">
        <hr class="adm-sidebar-divider" style="margin-bottom:12px;">
        <a href="<?= BASE_URL ?>/api/logout.php" class="adm-nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span><?= t('nav.logout') ?></span>
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
            <p class="adm-page-subtitle">View real-time medicine and vaccine stock levels.</p>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="adm-datetime-widget">
                    <span><i class="bi bi-calendar3"></i><span class="adm-dt-date"><?= htmlspecialchars($dashboardDateLabel) ?></span></span>
                </div>

                <div class="dropdown">
                    <button class="adm-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="adm-topbar-avatar"><?php require_once __DIR__ . '/../includes/shared/profile_photo_storage.php'; echo hc_render_sidebar_avatar(); ?></span>
                        <span>
                            <span class="d-block adm-topbar-name"><?= htmlspecialchars($adminProfile['name']) ?></span>
                            <span class="d-block adm-topbar-role"><?= htmlspecialchars($adminProfile['role']) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i><?= t('nav.profile_settings') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('nav.logout') ?></a></li>
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
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search medicine name, category..." autocomplete="off">
            </div>
            <button type="submit" class="mvi-btn-add"><i class="bi bi-search"></i> Search</button>
        </form>

        <!-- ============ MEDICINES TABLE ============ -->
        <?php require __DIR__ . '/../includes/admin/_mvi_medicines_table_readonly.php'; ?>

    </div>

    <div class="tab-pane fade" id="mvi-tab-vaccines" role="tabpanel">

        <!-- ============ FILTER & SEARCH TOOLBAR ============ -->
        <form class="mvi-toolbar" method="get" action="medicine_vaccine_inventory.php">
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
                <input type="text" name="vq" value="<?= htmlspecialchars($vaccineSearchTerm) ?>" placeholder="Search vaccine name, category..." autocomplete="off">
            </div>
            <button type="submit" class="mvi-btn-add"><i class="bi bi-search"></i> Search</button>
        </form>

        <!-- ============ VACCINES TABLE ============ -->
        <?php require __DIR__ . '/../includes/admin/_mvi_vaccines_table_readonly.php'; ?>

    </div>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
