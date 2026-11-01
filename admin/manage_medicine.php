<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Manage Medicine / Manage Vaccine (admin/manage_medicine.php)
 * ---------------------------------------------------------------------
 * A dedicated detail page — not a modal — reached from
 * admin/medicine_vaccine_inventory.php's own "Manage Medicine"/
 * "Manage Vaccine" link (?id=<real medicine doc id>&type=medicine|vaccine).
 * Same real item + full batch history pharmacist's and sub-admin's own
 * manage_medicine.php pages show (includes/admin/manage_medicine_firestore.php)
 * — but PURELY for monitoring. No Restock, no Dispense, no Archive, no
 * Edit, no Delete trigger anywhere on this page (same read-only scope
 * line admin/medicine_vaccine_inventory.php's own header comment
 * already draws for the rest of this collection) — the Batch Inventory
 * table's own Action column is dropped entirely rather than shown with
 * nothing real in it. The batch-number search box stays (a real UI
 * convenience, not a mutation), but the Restock button next to it does
 * not exist here at all — see subadmin/manage_medicine.php's own
 * header comment for the full role/action matrix across all three
 * manage_medicine.php pages.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()
require_once __DIR__ . '/../includes/shared/theme_helper.php'; // admin_theme_data_attr(), admin_theme_head_script()
require_once __DIR__ . '/../includes/shared/i18n.php'; // t()

// Only a signed-in admin may view this page.
if (($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/admin/manage_medicine_firestore.php';
// $adminProfile, $dashboardDateLabel,
// $medicineStatusMeta, $medicineExpiryBadgeMeta,
// $item, $itemBatches

// Sidebar nav — same Admin nav list as admin/medicine_vaccine_inventory.php.
// Medicine & Vaccine Inventory stays active (this is a sub-view of it).
$adminNavItems = [
    ['icon' => 'bi-grid-fill',           'label' => t('nav.dashboard'),           'href' => 'admin_dashboard.php'],
    ['icon' => 'bi-person-lines-fill',   'label' => 'Patient Management',         'href' => 'patient_management.php'],
    ['icon' => 'bi-capsule',             'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php', 'active' => true],
    ['icon' => 'bi-building',            'label' => t('nav.facility_management'), 'href' => 'facility_management.php'],    ['icon' => 'bi-people-fill',         'label' => t('nav.subadmin_management'), 'href' => 'subadmin_management.php'],
    ['icon' => 'bi-geo-alt-fill',        'label' => t('nav.gis_mapping'),         'href' => 'gis_mapping.php'],
    ['icon' => 'bi-shield-lock-fill',    'label' => t('nav.account_monitoring'),  'href' => 'account_monitoring.php'],
    ['icon' => 'bi-bell-fill',           'label' => t('nav.notifications'),       'href' => 'notifications.php', 'badge' => hc_admin_unread_notification_count()],
    ['icon' => 'bi-megaphone-fill',      'label' => 'Announcements',              'href' => 'announcements.php'],
    [
        'icon' => 'bi-clipboard-data-fill',
        'label' => t('nav.audit_logs'),
        'children' => [
            ['label' => 'Admin', 'href' => 'audit_logs.php?role=admin'],
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
<!-- Medicine & Vaccine Inventory page styles — this page's own "mm-" prefixed detail-view rules live here too -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medicine_vaccine_inventory.css') ?>">
<!-- .am-form-help/.am-detail-items-table utility classes, reused here without the modal markup that normally comes with this file -->
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
        <?php foreach ($adminNavItems as $navItem): ?>
        <?php if (!empty($navItem['children'])): ?>
        <li>
            <a href="#admNavAuditLogs" class="adm-nav-link adm-nav-toggle collapsed" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="admNavAuditLogs">
                <i class="bi <?= htmlspecialchars($navItem['icon']) ?>"></i>
                <span><?= htmlspecialchars($navItem['label']) ?></span>
                <i class="bi bi-chevron-down adm-nav-caret"></i>
            </a>
            <div class="collapse" id="admNavAuditLogs">
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
                <span class="adm-nav-badge" id="ntfSidebarBadge"><?= (int) $navItem['badge'] ?></span>
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

                <a href="notifications.php" class="adm-bell-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if (hc_admin_unread_notification_count() > 0): ?><span class="adm-badge-count"><?= hc_admin_unread_notification_count() ?></span><?php endif; ?>
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
                        <li><a class="dropdown-item" href="profile_settings.php"><i class="bi bi-gear me-2"></i><?= t('nav.profile_settings') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= t('nav.logout') ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ BACK LINK ============ -->
    <a href="medicine_vaccine_inventory.php" class="adm-back-link">
        <i class="bi bi-arrow-left"></i> Back to Medicine Inventory
    </a>

    <!-- ============ ITEM HEADER (read-only — no Restock/Edit/Delete) ============ -->
    <div class="mm-header">
        <div class="mm-header-identity">
            <span class="mm-thumb" style="background:<?= htmlspecialchars($item['colorMeta']['bg']) ?>; color:<?= htmlspecialchars($item['colorMeta']['color']) ?>;">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
            </span>
            <div>
                <h2 class="mm-name"><?= htmlspecialchars($item['name']) ?></h2>
                <p class="mm-subtitle">Batch monitoring</p>
            </div>
        </div>
        <div class="mm-header-actions">
            <div class="mm-search">
                <i class="bi bi-search"></i>
                <input type="text" id="mmBatchSearch" placeholder="Search batch number..." autocomplete="off">
            </div>
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

    <!-- ============ BATCH INVENTORY (read-only — no Action column) ============ -->
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
                        <th>Minimum Level</th>
                        <th>Reorder Level</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemBatches as $batch): ?>
                    <tr data-batch-number="<?= htmlspecialchars(strtolower($batch['displayBatchNumber'])) ?>">
                        <td class="mvi-strong"><?= htmlspecialchars($batch['displayBatchNumber']) ?></td>
                        <td><?= $item['dosage'] !== '' ? htmlspecialchars($item['dosage']) : 'Not Recorded' ?></td>
                        <td><?= number_format($batch['quantity']) ?> <?= htmlspecialchars($item['unit']) ?></td>
                        <td><?= number_format($batch['minLevel']) ?></td>
                        <td><?= number_format($batch['reorderLevel']) ?></td>
                        <td><?= htmlspecialchars($batch['expiryDate']) ?></td>
                        <td><span class="mvi-badge" style="background:<?= htmlspecialchars($batch['status']['bg']) ?>; color:<?= htmlspecialchars($batch['status']['color']) ?>;"><?= htmlspecialchars($batch['status']['label']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="am-form-help d-none" id="mmBatchNoMatch">No batches match your search.</p>
        <?php endif; ?>
    </div>

</main>

<!-- This page's own real item, purely so assets/js/manage_medicine.js's
     top-of-file guard (if (!item) return;) doesn't skip the one real
     feature this read-only page has: the batch-number search filter.
     No batches/restock/edit/delete data needed — none of those
     triggers exist here. -->
<script>
window.MM_ITEM = <?= json_encode([
    'id' => $item['id'],
    'type' => $item['type'],
    'name' => $item['name'],
]) ?>;
</script>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/theme.js') ?>"></script>
<script src="<?= asset_url('/assets/js/manage_medicine.js') ?>"></script>
</body>
</html>
