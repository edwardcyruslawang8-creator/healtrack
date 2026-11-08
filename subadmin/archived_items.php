<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Archived Items (subadmin/archived_items.php)
 * ---------------------------------------------------------------------
 * Sub-admin counterpart of pharmacist/archived_items.php, reached via
 * the "Archived Items" button on subadmin/medicine_vaccine_inventory.php
 * (same spot as the pharmacist's). Not its own sidebar entry — Medicine
 * & Vaccine Inventory stays "active" since this is a drill-down from it.
 *
 * Lists everything archived via api/archive-medicine.php (Medicines/
 * Vaccines tabs, search, category filter, pagination) with a real
 * Unarchive action (api/unarchive-medicine.php, which accepts subadmin)
 * wired by the shared assets/js/archived-items.js. Data:
 * includes/subadmin/archived_items_firestore.php.
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

require_once __DIR__ . '/../includes/subadmin/archived_items_firestore.php';
// $adminProfile, $dashboardDateLabel, $archivedSummary, $medicineExpiryBadgeMeta,
// $medicines, $medicineCategoryOptions, $searchTerm, $categoryFilter,
// $medicinesTotal, $medicinesCurrentPage, $medicinesTotalPages, $medicinePaginationWindow,
// $vaccines, $vaccineCategoryOptions, $vaccineSearchTerm, $vaccineCategoryFilter,
// $vaccinesTotal, $vaccinesCurrentPage, $vaccinesTotalPages, $vaccinePaginationWindow

// Sidebar nav — same Sub-Admin nav list as subadmin/medicine_vaccine_inventory.php.
// Medicine & Vaccine Inventory stays active (this page is a drill-down from it).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => t('nav.dashboard'),                    'href' => 'dashboard.php'],
    ['icon' => 'bi-person-lines-fill',      'label' => t('nav.patient_management'),           'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',                'label' => t('nav.medicine_vaccine_inventory'), 'href' => 'medicine_vaccine_inventory.php', 'active' => true],
    ['icon' => 'bi-box-seam-fill',          'label' => t('nav.stock_requests'),               'href' => 'stock_requests.php'],
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
            ['label' => t('nav.audit_logs_subadmin'),           'href' => 'audit_logs.php?role=subadmin'],
            ['label' => t('nav.audit_logs_doctor'),             'href' => 'audit_logs.php?role=doctor'],
            ['label' => t('nav.audit_logs_medstaff'),      'href' => 'audit_logs.php?role=healthcare_staff'],
            ['label' => t('nav.audit_logs_medtech'), 'href' => 'audit_logs.php?role=medical_technician'],
            ['label' => t('nav.audit_logs_radiotech'), 'href' => 'audit_logs.php?role=radio_technologist'],
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

if (!function_exists('arc_page_url')) {
    function arc_page_url(array $qs, string $pageParam, int $page): string
    {
        $qs[$pageParam] = $page;
        return 'archived_items.php?' . http_build_query($qs);
    }
}
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
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
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, tabs, table, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Medicine & Vaccine Inventory page styles (summary cards, tabs, toolbar, table) -->
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

    <!-- ============ ARCHIVED SUMMARY CARDS ============ -->
    <div class="mvi-summary-row mvi-summary-row--3">
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
        <!-- Search left, category right; no separate Search button — Enter
             submits the search (the form's only text field), and picking a
             category submits straight away. -->
        <form class="mvi-toolbar mvi-toolbar--search-first" method="get" action="archived_items.php">
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search medicine name, category..." autocomplete="off" enterkeyhint="search">
            </div>
            <select class="form-select mvi-filter-select" name="category" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($medicineCategoryOptions as $opt): ?>
                <option <?= $opt === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
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
                            <td><?= htmlspecialchars($med['unit']) ?><?php if (($med['piecesPerBox'] ?? 0) > 0): ?><span class="mvi-unit-sub"><?= htmlspecialchars(hc_packaging_label($med['piecesPerBox'])) ?></span><?php endif; ?></td>
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
        <form class="mvi-toolbar mvi-toolbar--search-first" method="get" action="archived_items.php">
            <input type="hidden" name="q" value="<?= htmlspecialchars($searchTerm) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
            <input type="hidden" name="page" value="<?= (int) $medicinesCurrentPage ?>">
            <div class="mvi-search">
                <i class="bi bi-search"></i>
                <input type="text" name="vq" value="<?= htmlspecialchars($vaccineSearchTerm) ?>" placeholder="Search vaccine name, category..." autocomplete="off" enterkeyhint="search">
            </div>
            <select class="form-select mvi-filter-select" name="vcategory" aria-label="Filter by category" onchange="this.form.submit()">
                <?php foreach ($vaccineCategoryOptions as $opt): ?>
                <option <?= $opt === $vaccineCategoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
            </select>
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
                            <td><?= htmlspecialchars($vac['unit']) ?><?php if (($vac['piecesPerBox'] ?? 0) > 0): ?><span class="mvi-unit-sub"><?= htmlspecialchars(hc_packaging_label($vac['piecesPerBox'])) ?></span><?php endif; ?></td>
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
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/archived-items.js') ?>"></script>
</body>
</html>
