<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Manage Medicine / Manage Vaccine (subadmin/manage_medicine.php)
 * ---------------------------------------------------------------------
 * A dedicated detail page — not a modal — reached from
 * subadmin/medicine_vaccine_inventory.php's own "Manage Medicine"/
 * "Manage Vaccine" link (?id=<real medicine doc id>&type=medicine|vaccine).
 * Same real batch data and layout as pharmacist/manage_medicine.php
 * (includes/subadmin/manage_medicine_firestore.php — see that file's
 * own header comment), with a sub-admin's own sidebar/topbar shell and
 * two extra header actions pharmacist's page doesn't have: Edit
 * (api/update-medicine.php, this item's own catalog info/thresholds —
 * reuses includes/pharmacist/add_medicine_modal.php's shared form,
 * wired fresh in assets/js/manage_medicine.js since this page doesn't
 * load the list-page JS that form's own edit wiring normally lives
 * in) and Delete (api/delete-medicine.php, permanent — redirects back
 * to the list on success). Both already accepted the subadmin role
 * server-side before this page existed.
 *
 * Deliberately NOT included here: Dispense. Dispensing medicine
 * directly to a patient (bypassing a prescription) stays a pharmacist-
 * only clinical action — a sub-admin's capabilities on this page are
 * administrative (Restock received stock, Edit/Delete catalog data,
 * Archive), not clinical. Each batch row's Action column reflects
 * this: Archive only, no Dispense button.
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

require_once __DIR__ . '/../includes/subadmin/manage_medicine_firestore.php';
// $adminProfile, $dashboardDateLabel,
// $medicineStatusMeta, $medicineExpiryBadgeMeta,
// $item, $itemBatches

// Sidebar nav — same Sub-Admin nav list as subadmin/medicine_vaccine_inventory.php.
// Medicine & Vaccine Inventory stays active (this is a sub-view of it).
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

$itemStatus = $medicineStatusMeta[$item['status']] ?? $medicineStatusMeta['available'];
$itemExpiryBadge = $medicineExpiryBadgeMeta[$item['expiryStatus']] ?? null;
$isVaccine = $item['type'] === 'vaccine';
?>
<!DOCTYPE html>
<html lang="en"<?= admin_theme_data_attr() ?>>
<head>
<?= admin_theme_head_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['name']) ?> — Manage <?= $isVaccine ? 'Vaccine' : 'Medicine' ?> — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<!-- Shared admin shell (sidebar, topbar, cards, back link, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Medicine & Vaccine Inventory page styles — this page's own "mm-" prefixed detail-view rules live here too -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medicine_vaccine_inventory.css') ?>">
<!-- Add/Edit + Restock modal styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/add_medicine_modal.css') ?>">
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
        <?php foreach ($adminNavItems as $navItem): ?>
        <?php if (!empty($navItem['children'])): ?>
        <li>
            <a href="#<?= htmlspecialchars($navItem['id'] ?? 'admNavAuditLogs') ?>" class="adm-nav-link adm-nav-toggle <?= !empty($navItem['active']) ? 'active' : '' ?> collapsed" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="<?= htmlspecialchars($navItem['id'] ?? 'admNavAuditLogs') ?>">
                <i class="bi <?= htmlspecialchars($navItem['icon']) ?>"></i>
                <span><?= htmlspecialchars($navItem['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse" id="<?= htmlspecialchars($navItem['id'] ?? 'admNavAuditLogs') ?>">
                <ul class="adm-nav-submenu">
                    <?php foreach ($navItem['children'] as $child): ?>
                    <li><a href="<?= htmlspecialchars($child['href']) ?>" class="adm-nav-sublink"><?= htmlspecialchars($child['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </li>
        <?php else: ?>
        <li>
            <a href="<?= htmlspecialchars($navItem['href']) ?>" class="adm-nav-link <?= !empty($navItem['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($navItem['icon']) ?>"></i>
                <span><?= htmlspecialchars($navItem['label']) ?></span>
                <?php if (!empty($navItem['badge'])): ?>
                <span class="sad-nav-badge"><?= (int) $navItem['badge'] ?></span>
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
            <h1><?= htmlspecialchars(t('nav.medicine_vaccine_inventory')) ?></h1>
            <nav class="mvi-breadcrumb" aria-label="Breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <a href="medicine_vaccine_inventory.php">Medicine &amp; Vaccine Inventory</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <span class="mvi-breadcrumb-current"><?= htmlspecialchars($item['name']) ?></span>
            </nav>
        </div>

        <div class="adm-topbar-right">
            <div class="adm-topbar-actions">
                <div class="sad-topbar-select-btn">
                    <i class="bi bi-calendar3"></i> <?= htmlspecialchars($dashboardDateLabel) ?>
                </div>
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

    <!-- ============ BACK LINK ============ -->
    <a href="medicine_vaccine_inventory.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Medicine Inventory
    </a>

    <!-- ============ ITEM HEADER ============ -->
    <div class="mm-header">
        <div class="mm-header-identity">
            <span class="mm-thumb" style="background:<?= htmlspecialchars($item['colorMeta']['bg']) ?>; color:<?= htmlspecialchars($item['colorMeta']['color']) ?>;">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
            </span>
            <div>
                <h2 class="mm-name"><?= htmlspecialchars($item['name']) ?></h2>
                <p class="mm-subtitle">Batch management and stock monitoring</p>
            </div>
        </div>
        <div class="mm-header-actions">
            <button type="button" id="mmEditBtn" class="mvi-manage-header-btn"><i class="bi bi-pencil"></i> Edit</button>
            <button type="button" id="mmDeleteBtn" class="mvi-manage-header-btn mvi-manage-header-btn-danger"><i class="bi bi-trash"></i> Delete</button>
            <div class="mm-search">
                <i class="bi bi-search"></i>
                <input type="text" id="mmBatchSearch" placeholder="Search batch number..." autocomplete="off">
            </div>
            <button type="button" id="mmRestockBtn" class="mm-restock-btn"><i class="bi bi-plus-lg"></i> Restock</button>
        </div>
    </div>

    <!-- ============ ITEM INFO ROW ============ -->
    <div class="adm-card mm-info-row">
        <div class="mm-info-cell">
            <span class="mm-info-label">Category</span>
            <span class="mm-info-value"><?= htmlspecialchars($item['category']) ?></span>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Unit</span>
            <span class="mm-info-value"><?= htmlspecialchars($item['unit']) ?></span>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Total Current Stock</span>
            <span class="mm-info-value"><?= number_format($item['currentStock']) ?> <?= htmlspecialchars($item['unit']) ?></span>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Minimum Level</span>
            <span class="mm-info-value"><?= number_format($item['minLevel']) ?> <?= htmlspecialchars($item['unit']) ?></span>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Reorder Level</span>
            <span class="mm-info-value"><?= number_format($item['reorderLevel']) ?> <?= htmlspecialchars($item['unit']) ?></span>
        </div>
    </div>

    <!-- Dosage Information + Description, when recorded. -->
    <?php if ($item['desc'] !== '' || $item['dosage'] !== ''): ?>
    <div class="adm-card mm-notes-row">
        <?php if ($item['desc'] !== ''): ?>
        <div class="mm-notes-cell"><span class="mm-info-label">Description</span><p><?= htmlspecialchars($item['desc']) ?></p></div>
        <?php endif; ?>
        <?php if ($item['dosage'] !== ''): ?>
        <div class="mm-notes-cell"><span class="mm-info-label">Dosage Information</span><p><?= htmlspecialchars($item['dosage']) ?></p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($item['expiryStatus'] === 'expired'): ?>
    <p class="mvi-manage-expired-note"><i class="bi bi-exclamation-circle"></i> This item has expired.</p>
    <?php endif; ?>

    <!-- ============ BATCH INVENTORY ============ -->
    <div class="adm-card mm-batch-card">
        <h3 class="mm-batch-title"><i class="bi bi-boxes"></i> Batch Inventory</h3>

        <?php if (empty($itemBatches)): ?>
        <p class="am-form-help">No batches recorded yet for this item.</p>
        <?php else: ?>
        <div class="adm-table-wrap">
            <table class="adm-table mm-batch-table" id="mmBatchTable">
                <thead>
                    <tr>
                        <th>Batch Number</th>
                        <th>Dosage Information</th>
                        <th>Current Stock</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemBatches as $batch): ?>
                    <tr data-batch-number="<?= htmlspecialchars(strtolower($batch['displayBatchNumber'])) ?>">
                        <td class="mvi-strong"><?= htmlspecialchars($batch['displayBatchNumber']) ?></td>
                        <td><?= $item['dosage'] !== '' ? htmlspecialchars($item['dosage']) : 'Not Recorded' ?></td>
                        <td><?= number_format($batch['quantity']) ?> <?= htmlspecialchars($item['unit']) ?></td>
                        <td><?= htmlspecialchars($batch['expiryDate']) ?></td>
                        <td><span class="mvi-badge" style="background:<?= htmlspecialchars($batch['status']['bg']) ?>; color:<?= htmlspecialchars($batch['status']['color']) ?>;"><?= htmlspecialchars($batch['status']['label']) ?></span></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="mvi-archive-btn mm-item-archive"><i class="bi bi-archive"></i> Archive</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="am-form-help d-none" id="mmBatchNoMatch">No batches match your search.</p>
        <?php endif; ?>
    </div>

</main>

<?php
// Restock modal + Add/Edit form (Edit, this page's own extra action) —
// see subadmin/manage_medicine.php's own header comment.
require __DIR__ . '/../includes/pharmacist/add_medicine_modal.php';
require __DIR__ . '/../includes/pharmacist/medicine_action_modals.php';
?>

<!-- This page's own real item + batch list, for assets/js/manage_medicine.js
     (Edit/Delete/Archive/Restock wiring, and the batch-number search
     filter) — server-rendered, no AJAX fetch needed for data this page
     already has. -->
<script>
window.MM_ITEM = <?= json_encode([
    'id' => $item['id'],
    'type' => $item['type'],
    'name' => $item['name'],
    'category' => $item['category'],
    'unit' => $item['unit'],
    'currentStock' => $item['currentStock'],
    'minLevel' => $item['minLevel'],
    'reorderLevel' => $item['reorderLevel'],
    'maxLevel' => $item['maxLevel'],
    'desc' => $item['desc'],
    'dosage' => $item['dosage'],
    'expiryDate' => $item['expiryDate'],
    'expiryStatus' => $item['expiryStatus'],
]) ?>;
window.MM_BATCHES = <?= json_encode(array_map(fn ($b) => [
    'id' => $b['id'],
    'displayBatchNumber' => $b['displayBatchNumber'],
    'quantity' => $b['quantity'],
    'expiryDate' => $b['expiryDate'],
], $itemBatches)) ?>;
</script>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/manage_medicine.js') ?>"></script>
</body>
</html>
