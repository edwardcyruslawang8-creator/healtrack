<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Manage Medicine / Manage Vaccine (pharmacist/manage_medicine.php)
 * ---------------------------------------------------------------------
 * A dedicated detail page — not a modal — reached from
 * pharmacist/medicine_vaccine_inventory.php's own "Manage Medicine"/
 * "Manage Vaccine" link (?id=<real medicine doc id>&type=medicine|vaccine).
 * Same sidebar/topbar/summary-cards shell as that page (and every other
 * pharmacist/*.php), with Medicine & Vaccine Inventory still marked
 * active in the sidebar, since this IS a sub-view of that section.
 *
 * The real batch data (includes/pharmacist/manage_medicine_firestore.php)
 * is server-rendered for first paint — every real batch this item has
 * ever had (including fully-depleted ones, correctly shown "Out of
 * Stock"), FEFO-ordered (soonest-expiring first). The Batch Inventory
 * table's own search box (filters rows by batch number, client-side —
 * assets/js/manage_medicine.js) sits directly left of the Restock
 * button in this page's own header row.
 *
 * Dispense/Archive/Restock all reuse the SAME real endpoints the old
 * #mviManageModal used (api/dispense-without-prescription.php,
 * api/archive-medicine.php, api/restock-medicine.php) — only where
 * they're triggered from changed, not what they do. FEFO still governs
 * which real batch an actual dispense draws down from regardless of
 * which row's own Dispense button was clicked (hc_decrement_medicine_stock_fefo()) —
 * there is no real "dispense from exactly this one batch" operation,
 * same as before.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/manage_medicine_firestore.php';
// $staffProfile, $dashboardDateLabel,
// $medicineStatusMeta, $medicineExpiryBadgeMeta,
// $item, $itemBatches

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

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

$itemStatus = $medicineStatusMeta[$item['status']] ?? $medicineStatusMeta['available'];
$itemExpiryBadge = $medicineExpiryBadgeMeta[$item['expiryStatus']] ?? null;
$isVaccine = $item['type'] === 'vaccine';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="<?= asset_url('/assets/images/main_logo.png') ?>">
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
<!-- Pharmacist Dashboard styles, reused here for the nav badge + topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Medicine & Vaccine Inventory page styles — summary cards + this page's own "mm-" prefixed detail-view rules live here too -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/medicine_vaccine_inventory.css') ?>">
<!-- Add/Restock/Dispense modal styles -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/quick-search.css') ?>">
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
        <div class="adm-logo-badge"><img src="<?= asset_url('/assets/images/main_logo.png') ?>" alt="HEALTrack logo" class="adm-logo-img"></div>
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
        <?php foreach ($staffNavItems as $navItem): ?>
        <li>
            <a href="<?= htmlspecialchars($navItem['href']) ?>" class="adm-nav-link <?= !empty($navItem['active']) ? 'active' : '' ?>">
                <i class="bi <?= htmlspecialchars($navItem['icon']) ?>"></i>
                <span><?= htmlspecialchars($navItem['label']) ?></span>
                <?php if (!empty($navItem['badge'])): ?>
                <span class="phm-nav-badge"><?= (int) $navItem['badge'] ?></span>
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
                <a href="medicine_vaccine_inventory.php">Medicine &amp; Vaccine Inventory</a>
                <span class="mvi-breadcrumb-sep">&gt;</span>
                <span class="mvi-breadcrumb-current"><?= htmlspecialchars($item['name']) ?></span>
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
                <?php $bw = $item['type'] === 'vaccine' ? 'Lot' : 'Batch'; // vaccines are tracked by lot, medicines by batch ?>
                <p class="mm-subtitle"><?= $bw ?> management and stock monitoring</p>
            </div>
        </div>
        <div class="mm-header-actions">
            <div class="mm-search">
                <i class="bi bi-search"></i>
                <input type="text" id="mmBatchSearch" placeholder="Search <?= $item['type'] === 'vaccine' ? 'lot' : 'batch' ?> number..." autocomplete="off">
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
            <?php if ($item['piecesPerBox'] > 0): ?><span class="mm-info-sub"><?= htmlspecialchars(hc_packaging_label($item['piecesPerBox'])) ?></span><?php endif; ?>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Total Current Stock</span>
            <span class="mm-info-value"><?= htmlspecialchars(hc_qty_unit($itemUsableStock, $item['unit'])) ?></span>
            <?php if (hc_box_breakdown($itemUsableStock, $item['piecesPerBox'], $item['unit']) !== ''): ?><span class="mm-info-sub"><?= htmlspecialchars(hc_box_breakdown($itemUsableStock, $item['piecesPerBox'], $item['unit'])) ?></span><?php endif; ?>
            <?php if ($itemExpiredStock > 0): ?><span class="mm-info-sub mm-info-sub-expired"><?= htmlspecialchars(hc_qty_unit($itemExpiredStock, $item['unit'])) ?> expired, not counted</span><?php endif; ?>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Minimum Level</span>
            <span class="mm-info-value"><?= htmlspecialchars(hc_qty_unit($item['minLevel'], $item['unit'])) ?></span>
        </div>
        <div class="mm-info-cell">
            <span class="mm-info-label">Reorder Level</span>
            <span class="mm-info-value"><?= htmlspecialchars(hc_qty_unit($item['reorderLevel'], $item['unit'])) ?></span>
        </div>
    </div>

    <!-- Dosage Information + Description, when recorded — not part of
         the mockup's info row (that's per-batch, repeated — see the
         table below), but still real medicine-level data worth
         showing once rather than dropping silently. -->
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

    <!-- Shown only when EVERY batch holding real stock is expired — a
         real per-batch check now (includes/pharmacist/manage_medicine_firestore.php's
         $itemAllStockExpired), not the item's own single, stale
         expiryStatus field. api/dispense-without-prescription.php
         re-checks the real batch's own expiry server-side too. -->
    <?php if ($itemAllStockExpired): ?>
    <p class="mvi-manage-expired-note"><i class="bi bi-exclamation-circle"></i> This item has expired and can no longer be dispensed.</p>
    <?php endif; ?>

    <!-- ============ BATCH INVENTORY ============ -->
    <div class="adm-card mm-batch-card">
        <h3 class="mm-batch-title"><i class="bi bi-boxes"></i> <?= $bw ?> Inventory</h3>

        <?php $expiredBatchCount = count(array_filter($itemBatches, fn ($b) => $b['status']['key'] === 'expired')); ?>
        <?php if ($expiredBatchCount > 0): ?>
        <p class="mm-expired-batches-note"><i class="bi bi-exclamation-triangle-fill"></i>
            <?= $expiredBatchCount === 1 ? '1 ' . strtolower($bw) . ' has' : $expiredBatchCount . ' ' . strtolower($bw) . ($bw === 'Batch' ? 'es' : 's') . ' have' ?> expired and can't be dispensed. Use "Archive Expired <?= $bw ?>" to remove expired stock from inventory.</p>
        <?php endif; ?>

        <?php if (empty($itemBatches)): ?>
        <p class="am-form-help">No <?= $bw === 'Lot' ? 'lots' : 'batches' ?> recorded yet for this item.</p>
        <?php else: ?>
        <div class="adm-table-wrap">
            <table class="adm-table mm-batch-table" id="mmBatchTable">
                <thead>
                    <tr>
                        <th><?= $bw ?> Number</th>
                        <th>Dosage Information</th>
                        <th>Current Stock</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itemBatches as $batch): ?>
                    <tr data-batch-number="<?= htmlspecialchars(strtolower($batch['displayBatchNumber'])) ?>"<?= $batch['status']['key'] === 'expired' ? ' class="mm-batch-row-expired"' : '' ?>>
                        <td class="mvi-strong"><?= htmlspecialchars($batch['displayBatchNumber']) ?></td>
                        <td><?= $item['dosage'] !== '' ? htmlspecialchars($item['dosage']) : 'Not Recorded' ?></td>
                        <td><?= htmlspecialchars(hc_qty_unit($batch['quantity'], $item['unit'])) ?><?php if (hc_box_breakdown($batch['quantity'], $item['piecesPerBox'], $item['unit']) !== ''): ?><span class="mvi-unit-sub"><?= htmlspecialchars(hc_box_breakdown($batch['quantity'], $item['piecesPerBox'], $item['unit'])) ?></span><?php endif; ?></td>
                        <td><?= htmlspecialchars($batch['expiryDate']) ?></td>
                        <td><span class="mvi-badge" style="background:<?= htmlspecialchars($batch['status']['bg']) ?>; color:<?= htmlspecialchars($batch['status']['color']) ?>;"><?= htmlspecialchars($batch['status']['label']) ?></span></td>
                        <td>
                            <div class="adm-action-btns">
                                <!-- Dispense is item-level FEFO (it only ever draws from
                                     non-expired batches — hc_decrement_medicine_stock_fefo()),
                                     repeated per row for convenience. An EXPIRED batch's own
                                     row gets it disabled, so it never looks like this expired
                                     stock can be dispensed; instead that row's Archive is the
                                     prominent action and archives only this expired batch
                                     (api/archive-medicine-batch.php), never the whole item —
                                     a still-valid batch keeps its stock. Non-expired batches
                                     have no Archive button at all. Which rows count as expired
                                     is decided on every page load from the batch's expiry date
                                     vs. today (hc_batch_status()), so the button appears
                                     automatically once a batch expires. -->
                                <?php if ($batch['status']['key'] === 'expired'): ?>
                                <button type="button" class="mm-dispense-btn" disabled title="This <?= strtolower($bw) ?> has expired and can't be dispensed"><i class="bi bi-capsule"></i> Dispense</button>
                                <button type="button" class="mm-archive-expired-btn mm-batch-archive" data-batch-id="<?= htmlspecialchars($batch['id']) ?>" data-batch-number="<?= htmlspecialchars($batch['displayBatchNumber']) ?>" data-batch-qty="<?= htmlspecialchars(hc_qty_unit($batch['quantity'], $item['unit'])) ?>"><i class="bi bi-archive-fill"></i> Archive Expired <?= $bw ?></button>
                                <?php else: ?>
                                <button type="button" class="mm-dispense-btn mm-item-dispense" <?= $itemAllStockExpired || $batch['quantity'] <= 0 ? 'disabled' : '' ?>><i class="bi bi-capsule"></i> Dispense</button>
                                <?php endif; ?>
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
// Restock/Dispense (No Prescription)/Stock History modals — the same
// shared partial pharmacist/medicine_vaccine_inventory.php itself
// includes (subadmin's own page also includes it, for its Dispense/
// Stock History row actions — see that file's own header comment).
require __DIR__ . '/../includes/pharmacist/medicine_action_modals.php';
?>

<!-- This page's own real item + batch list, for assets/js/manage_medicine.js
     (Dispense/Archive/Restock wiring, and the batch-number search
     filter) — server-rendered, no AJAX fetch needed for data this page
     already has. -->
<script>
window.MM_ITEM = <?= json_encode([
    'id' => $item['id'],
    'type' => $item['type'],
    'name' => $item['name'],
    'unit' => $item['unit'],
    'piecesPerBox' => $item['piecesPerBox'],
    'currentStock' => $item['currentStock'],
    // What can actually be dispensed: stock in non-expired batches only
    // (the same batches FEFO draws from), not currentStock, which also
    // counts expired stock still waiting to be archived.
    'dispensableStock' => $itemUsableStock,
    'expiryStatus' => $item['expiryStatus'],
    'dosage' => $item['dosage'],
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
<script src="<?= asset_url('/assets/js/quick-search.js') ?>"></script>
<script src="<?= asset_url('/assets/js/manage_medicine.js') ?>"></script>
<!-- Maroon-themed calendar for every date input (the browser's own date
     popup can't be recolored) -- flatpickr + assets/js/hc-datepicker.js. -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-datepicker.css') ?>">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script src="<?= asset_url('/assets/js/hc-datepicker.js') ?>"></script>
</body>
</html>
