<?php
/**
 * =====================================================================
 * HEALTrack — Health Information System
 * Analytics & Reports (pharmacist/analytics_reports.php)
 * ---------------------------------------------------------------------
 * Top-level Pharmacist section (its own sidebar nav item) — same shape
 * as pharmacist/stock_requests.php. Reuses the exact same sidebar/
 * topbar shell as the rest of pharmacist/*.php (280px dark-red gradient
 * sidebar, "adm-"/"phm-" prefixed atoms, assets/css/admin-dashboard.css),
 * plus subadmin/analytics_reports.php's own KPI-card/chart-card/donut/
 * ranked-bar-list CSS patterns ("sad-"/"arp-" prefixed,
 * assets/css/subadmin_dashboard.css + assets/css/analytics_reports.css)
 * so the chart language reads as one system across every role.
 * Analytics & Reports stays active in the sidebar, since this IS that
 * page.
 *
 * CONNECTED to real Firestore data, mostly scoped to the signed-in
 * pharmacist's OWN stock requests/dispensing — Inventory Status Summary
 * is facility-wide on purpose (stock levels aren't owned by one
 * pharmacist) — see
 * includes/pharmacist/analytics_reports_pharmacist_firestore.php's
 * header comment. "My Stock Requests Over Time" has a real
 * Daily/Weekly/Monthly range switch — real ?requestsRange=... links,
 * intercepted by assets/js/analytics_reports.js for a smooth in-place
 * chart refresh via api/get-stock-requests-trend.php (no full page
 * reload) — same generic data-attribute-driven mechanism
 * subadmin/analytics_reports.php's own "Consultations Over Time" card
 * uses (see that JS file's own header comment).
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL, asset_url()

// Only a signed-in pharmacist may view this page.
if (($_SESSION['role'] ?? null) !== 'pharmacist') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

require_once __DIR__ . '/../includes/pharmacist/analytics_reports_pharmacist_firestore.php';
// $staffProfile, $dashboardDateLabel, $analyticsDateRangeLabel, $kpiCards,
// $trendLabels, $trendCounts, $requestsByStatus, $requestsByStatusTotal,
// $inventoryStatusSummary, $inventoryStatusTotal, $topMedicinesDispensed, $topMedicinesDispensedMax,
// $stockMovementSummary, $stockMovementTotal, $irExpiryAllItems, $irExpiryTop5,
// $irExpiryExpiredCount, $irExpiryExpiringSoonCount
// hc_pharmacist_unread_notification_count() (sidebar/topbar badge) already
// comes from config/constants.php, required above.

require_once __DIR__ . '/../includes/pharmacist/predictive_overview_firestore.php';
// $predictiveOverviewCards, $predictiveOverviewTrend, $predictiveOverviewTopAlerts
// — Medicine & Vaccine Shortage + Inventory Replenishment only (see
// that file's header comment for why disease/consultation forecasting
// isn't part of this role's card).

require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php'; // hc_get_pending_pharmacy_order_count() for the sidebar badge below

// Sidebar nav — same Pharmacist nav list as pharmacist/dashboard.php.
// Analytics & Reports stays active (this IS that page).
$staffNavItems = [
    ['icon' => 'bi-grid-fill',              'label' => 'Dashboard',                    'href' => 'dashboard.php'],
    ['icon' => 'bi-capsule',                'label' => 'Medicine & Vaccine Inventory', 'href' => 'medicine_vaccine_inventory.php'],
    ['icon' => 'bi-clipboard2-pulse-fill',  'label' => 'Prescription Queue',           'href' => 'prescription_queue.php', 'badge' => hc_get_pending_pharmacy_order_count()],
    ['icon' => 'bi-box-seam-fill',          'label' => 'Stock Requests',               'href' => 'stock_requests.php'],
    ['icon' => 'bi-bar-chart-fill',         'label' => 'Analytics & Reports',          'href' => 'analytics_reports.php', 'active' => true],
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
<title>Analytics &amp; Reports — HEALTrack</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Font Awesome (available per project spec; Bootstrap Icons cover this page's icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Shared admin shell (sidebar, topbar, cards, etc.) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/admin-dashboard.css') ?>">
<!-- Pharmacist Dashboard styles, reused here for the topbar select buttons -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/pharmacist_dashboard.css') ?>">
<!-- Sub-Admin Dashboard styles, reused here for the chart-card/legend/ranked-bar patterns -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/subadmin_dashboard.css') ?>">
<!-- Analytics & Reports page styles (KPI cards, ranked list, footer notice) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/analytics_reports.css') ?>">
<link rel="stylesheet" href="<?= asset_url('/assets/css/inventory_reports.css') ?>">
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
            <h1>Analytics &amp; Reports</h1>
            <p class="adm-page-subtitle">Your own stock requests and dispensing — <?= htmlspecialchars($analyticsDateRangeLabel) ?> trend, all-time totals.</p>
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

    <!-- ============ KPI SUMMARY CARDS ============ -->
    <div class="arp-kpi-row">
        <?php foreach ($kpiCards as $kpi): ?>
        <div class="adm-card arp-kpi-card">
            <div class="arp-kpi-icon" style="background:<?= htmlspecialchars($kpi['iconBg']) ?>; color:<?= htmlspecialchars($kpi['iconColor']) ?>;">
                <i class="bi <?= htmlspecialchars($kpi['icon']) ?>"></i>
            </div>
            <div class="arp-kpi-text">
                <span class="arp-kpi-label"><?= htmlspecialchars($kpi['label']) ?></span>
                <span class="arp-kpi-value"><?= number_format($kpi['value']) ?></span>
                <span class="arp-kpi-trend <?= $kpi['trend'] === 'down' ? 'down' : 'up' ?>">
                    <i class="bi <?= $kpi['trend'] === 'down' ? 'bi-arrow-down-short' : 'bi-arrow-up-short' ?>"></i> <?= htmlspecialchars($kpi['sub']) ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ PREDICTIVE & PRESCRIPTIVE ANALYTICS (PREVIEW) ============
         Medicine & Vaccine Shortage + Inventory Replenishment only — see
         includes/pharmacist/predictive_overview_firestore.php's header
         comment. -->
    <?php
    $predictiveOverviewLinkHref = null; // no dedicated full predictive page for this role yet
    require __DIR__ . '/../includes/admin/predictive_overview_card.php';
    ?>

    <!-- ============ TREND + REQUEST STATUS + INVENTORY STATUS ============ -->
    <div class="sad-row-3">

        <div class="adm-card sad-chart-card" id="myRequestsTrendCard" data-range-endpoint="../api/get-stock-requests-trend.php" data-range-param="requestsRange">
            <div class="sad-card-header">
                <h2>My Stock Requests Over Time</h2>
                <div class="dropdown">
                    <button class="sad-range-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($requestsTrendRangeOptions[$requestsTrendRange]) ?></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($requestsTrendRangeOptions as $rangeKey => $rangeLabel): ?>
                        <li><a class="dropdown-item range-switch-link <?= $rangeKey === $requestsTrendRange ? 'active' : '' ?>" href="?requestsRange=<?= $rangeKey ?>" data-range="<?= $rangeKey ?>"><?= htmlspecialchars($rangeLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="sad-canvas-box">
                <canvas id="requestsOverTimeChart"></canvas>
            </div>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>My Requests by Status</h2></div>
            <?php if ($requestsByStatusTotal > 0): ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="requestsByStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($requestsByStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($requestsByStatus as $rs): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($rs['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($rs['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($rs['value']) ?> (<?= rtrim(rtrim(number_format($rs['value'] / $requestsByStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="adm-table-empty">No stock requests yet.</p>
            <?php endif; ?>
        </div>

        <div class="adm-card sad-chart-card">
            <div class="sad-card-header"><h2>Inventory Status Summary</h2></div>
            <?php if ($inventoryStatusTotal > 0): ?>
            <div class="adm-donut-wrap">
                <div class="adm-donut-canvas-box">
                    <canvas id="inventoryStatusChart"></canvas>
                    <div class="adm-donut-center">
                        <div class="adm-donut-total"><?= number_format($inventoryStatusTotal) ?></div>
                        <div class="adm-donut-total-label">Total Items</div>
                    </div>
                </div>
            </div>
            <div class="sad-donut-legend">
                <?php foreach ($inventoryStatusSummary as $inv): ?>
                <div class="sad-legend-row">
                    <span class="sad-legend-dot" style="background:<?= htmlspecialchars($inv['color']) ?>;"></span>
                    <span class="sad-legend-label"><?= htmlspecialchars($inv['label']) ?></span>
                    <span class="sad-legend-value"><?= number_format($inv['value']) ?> (<?= rtrim(rtrim(number_format($inv['value'] / $inventoryStatusTotal * 100, 1), '0'), '.') ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
            <a href="medicine_vaccine_inventory.php" class="arp-report-link"><i class="bi bi-box-seam-fill"></i> View Inventory</a>
            <?php else: ?>
            <p class="adm-table-empty">No inventory items on record yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============ STOCK MOVEMENT SUMMARY + INVENTORY EXPIRY MONITOR ============
         Same real cards as subadmin/inventory_reports.php's own (facility-
         wide inventory data, not owned by one pharmacist) — see
         includes/shared/inventory_movement_monitor.php's own header
         comment. assets/css/inventory_reports.css / assets/js/inventory_movement_cards.js
         are shared verbatim with that page. -->
    <div class="ir-grid-60-40">

        <!-- ===== Stock Movement Summary ===== -->
        <div class="adm-card ir-movement-card">
            <div class="adm-card-header ir-card-header">
                <h2>Stock Movement Summary</h2>
                <div class="ir-header-controls">
                    <select class="form-select ir-range-select" id="irMovementRange" aria-label="Date range">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    <div class="ir-custom-range" id="irMovementCustomRange" hidden>
                        <input type="date" id="irMovementStartDate" aria-label="Start date">
                        <span>&ndash;</span>
                        <input type="date" id="irMovementEndDate" aria-label="End date">
                        <button type="button" class="ir-apply-btn" id="irMovementApplyRange">Apply</button>
                    </div>
                </div>
            </div>

            <div class="ir-movement-summary">
                <div class="ir-stat"><span class="ir-stat-value" id="irMovementTotalReceived"><?= number_format($stockMovementTotal['received']) ?></span><span class="ir-stat-label">Total Received</span></div>
                <div class="ir-stat"><span class="ir-stat-value" id="irMovementTotalIssued"><?= number_format($stockMovementTotal['issued']) ?></span><span class="ir-stat-label">Total Issued</span></div>
                <div class="ir-stat"><span class="ir-stat-value" id="irMovementClosingStock"><?= number_format($stockMovementTotal['closing']) ?></span><span class="ir-stat-label">Closing Stock</span></div>
            </div>

            <div class="adm-table-wrap">
                <table class="adm-table ir-movement-table" style="min-width:0;">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Opening Stock</th>
                            <th>Received</th>
                            <th>Issued</th>
                            <th>Closing Stock</th>
                        </tr>
                    </thead>
                    <tbody id="irMovementTableBody">
                        <?php foreach ($stockMovementSummary as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= number_format($row['opening']) ?></td>
                            <td><?= number_format($row['received']) ?></td>
                            <td><?= number_format($row['issued']) ?></td>
                            <td><?= number_format($row['closing']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="ir-movement-total-row">
                            <td>Total</td>
                            <td id="irMovementFootOpening"><?= number_format($stockMovementTotal['opening']) ?></td>
                            <td id="irMovementFootReceived"><?= number_format($stockMovementTotal['received']) ?></td>
                            <td id="irMovementFootIssued"><?= number_format($stockMovementTotal['issued']) ?></td>
                            <td id="irMovementFootClosing"><?= number_format($stockMovementTotal['closing']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="ir-card-actions">
                <button type="button" class="ir-viewall-btn" data-bs-toggle="modal" data-bs-target="#irMovementsModal"><i class="bi bi-list-ul"></i> View Stock Movements</button>
            </div>
        </div>

        <!-- ===== Inventory Expiry Monitor ===== -->
        <div class="adm-card ir-expiry-card">
            <div class="adm-card-header ir-card-header">
                <h2>Inventory Expiry Monitor</h2>
            </div>
            <div class="ir-expiry-badges">
                <span class="ir-expiry-badge ir-expiry-badge-expired"><i class="bi bi-exclamation-octagon-fill"></i> <?= (int) $irExpiryExpiredCount ?> Expired</span>
                <span class="ir-expiry-badge ir-expiry-badge-soon"><i class="bi bi-clock-history"></i> <?= (int) $irExpiryExpiringSoonCount ?> Expiring Soon</span>
            </div>
            <div class="ir-expiry-filters" id="irExpiryFilterSeg" role="tablist" aria-label="Filter">
                <button type="button" class="ir-expiry-filter-btn active" data-filter="relevant">All</button>
                <button type="button" class="ir-expiry-filter-btn" data-filter="expired">Expired</button>
                <button type="button" class="ir-expiry-filter-btn" data-filter="expiring_soon">Next 30 Days</button>
            </div>

            <div class="ir-expiry-list" id="irExpiryList">
                <?php foreach ($irExpiryTop5 as $r): ?>
                <div class="ir-expiry-row">
                    <div class="ir-expiry-row-main">
                        <span class="ir-expiry-row-name"><?= htmlspecialchars($r['name']) ?></span>
                        <span class="ir-expiry-row-meta"><?= htmlspecialchars($r['category']) ?> &middot; Batch: <?= htmlspecialchars($r['batchNo']) ?></span>
                    </div>
                    <div class="ir-expiry-row-side">
                        <span class="ir-expiry-status-badge ir-expiry-status-<?= htmlspecialchars($r['status']) ?>">
                            <?php if ($r['status'] === 'expired'): ?>Expired <?= abs((int) $r['daysFromToday']) ?>d ago
                            <?php elseif ($r['status'] === 'today'): ?>Expires Today
                            <?php else: ?><?= (int) $r['daysFromToday'] ?>d left
                            <?php endif; ?>
                        </span>
                        <span class="ir-expiry-row-qty"><?= number_format($r['quantity']) ?> <?= htmlspecialchars($r['unit']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($irExpiryTop5 === []): ?>
                <div class="ir-empty"><i class="bi bi-check-circle-fill"></i><p>No expired or soon-to-expire batches right now.</p></div>
                <?php endif; ?>
            </div>

            <div class="ir-card-actions">
                <button type="button" class="ir-viewall-btn" data-bs-toggle="modal" data-bs-target="#irExpiryAllModal"><i class="bi bi-table"></i> View All Items</button>
            </div>
        </div>

    </div>

    <!-- ===== View Stock Movements modal ===== -->
    <div class="modal fade" id="irMovementsModal" tabindex="-1" aria-labelledby="irMovementsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content ir-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="irMovementsModalLabel">Stock Movement Transactions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="ir-modal-note">Every real stock receipt and dispense recorded in the date range currently selected above.</p>
                    <div class="adm-table-wrap ir-modal-table-wrap">
                        <table class="adm-table ir-modal-table" style="min-width:0;">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody id="irMovementsModalTableBody"></tbody>
                        </table>
                        <p class="ir-modal-empty" id="irMovementsModalEmpty" style="display:none;">No real stock movements recorded in this date range.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== View All Items (Expiry Monitor) modal ===== -->
    <div class="modal fade" id="irExpiryAllModal" tabindex="-1" aria-labelledby="irExpiryAllModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content ir-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="irExpiryAllModalLabel">Complete Inventory Expiry Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ir-modal-toolbar">
                        <div class="ir-modal-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="irExpiryAllSearch" placeholder="Search item name...">
                        </div>
                        <div class="ir-expiry-filters" id="irExpiryAllFilterSeg" role="tablist" aria-label="Filter">
                            <button type="button" class="ir-expiry-filter-btn active" data-filter="all">All</button>
                            <button type="button" class="ir-expiry-filter-btn" data-filter="expired">Expired</button>
                            <button type="button" class="ir-expiry-filter-btn" data-filter="expiring_soon">Next 30 Days</button>
                        </div>
                        <select class="form-select ir-sort-select" id="irExpiryAllSort" aria-label="Sort">
                            <option value="urgency" selected>Most Urgent First</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="expiry-asc">Expiry Date (Soonest)</option>
                            <option value="qty-desc">Quantity (Highest)</option>
                        </select>
                    </div>
                    <div class="adm-table-wrap ir-modal-table-wrap">
                        <table class="adm-table ir-modal-table" style="min-width:0;">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Batch No.</th>
                                    <th>Expiry Date</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="irExpiryAllTableBody"></tbody>
                        </table>
                        <p class="ir-modal-empty" id="irExpiryAllEmpty" style="display:none;">No items match your search.</p>
                    </div>
                    <div class="ir-modal-pagination" id="irExpiryAllPagination"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ TOP MEDICINES I DISPENSED ============ -->
    <div class="adm-card sad-chart-card">
        <div class="sad-card-header"><h2>Top 5 Medicines/Vaccines I Dispensed</h2></div>
        <?php if ($topMedicinesDispensed !== []): ?>
        <div class="arp-ranked-axis-label">Quantity</div>
        <div class="sad-condition-list arp-ranked-list">
            <?php foreach ($topMedicinesDispensed as $m): ?>
            <div class="sad-condition-row">
                <span class="sad-condition-label"><?= htmlspecialchars($m['label']) ?></span>
                <div class="sad-condition-bar-track">
                    <div class="sad-condition-bar-fill" style="width:<?= round($m['value'] / $topMedicinesDispensedMax * 100) ?>%;"></div>
                </div>
                <span class="sad-condition-value"><?= number_format($m['value']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="adm-table-empty">You haven't dispensed anything yet.</p>
        <?php endif; ?>
    </div>

    <!-- ============ OPERATIONAL TRENDS (comparative/anomaly analysis) ============
         Real mean/standard-deviation anomaly check (same method the
         disease/consultation-volume checks already use) over MY OWN
         real stock requests — hc_operational_trends_overview()
         (includes/shared/analytics_comparison.php). Self-contained
         <style> since this page doesn't load predictive_analytics.css. -->
    <style>
    .pn-severity.unusual{
        display: inline-flex; align-items: center; font-size: 10.5px; font-weight: 700;
        letter-spacing: .3px; text-transform: uppercase; padding: 3px 9px; border-radius: 50px;
        white-space: nowrap; background: #F1E8FD; color: #8B5CF6; margin-left: 6px;
    }
    .arp-op-trend-note{ font-size: 12.5px; color: var(--adm-text-muted, #6B7280); margin: 10px 0 0; }
    </style>
    <div class="adm-card sad-chart-card">
        <div class="sad-card-header">
            <h2>My Stock Request Volume (Last 30 Days)</h2>
            <?php if ($operationalTrends['hasData'] && $operationalTrends['volumeAnomaly']): ?>
            <span class="pn-severity unusual" title="More than 2 standard deviations from my own 30-day baseline">Unusual</span>
            <?php endif; ?>
        </div>
        <?php if (!$operationalTrends['hasData']): ?>
        <p class="adm-table-empty">No stock requests on record yet.</p>
        <?php else: ?>
        <div class="sad-canvas-box">
            <canvas id="operationalVolumeChart"></canvas>
        </div>
        <p class="arp-op-trend-note">Recent 7-day average: <strong><?= $operationalTrends['volumeRecentAvg'] ?></strong>/day vs a 30-day baseline of <strong><?= $operationalTrends['volumeBaselineAvg'] ?></strong>/day.</p>
        <?php if ($operationalTrends['hasTurnaroundData']): ?>
        <p class="arp-op-trend-note">
            Average processing time (request to decision): <strong><?= $operationalTrends['turnaroundRecentAvgHours'] ?>h</strong> recently vs a <strong><?= $operationalTrends['turnaroundBaselineAvgHours'] ?>h</strong> baseline
            <?php if ($operationalTrends['turnaroundAnomaly']): ?><span class="pn-severity unusual">Unusual</span><?php endif; ?>
        </p>
        <?php else: ?>
        <p class="arp-op-trend-note">Not enough decided requests yet to compare real processing time.</p>
        <?php endif; ?>
        <script>window.OPERATIONAL_TRENDS = <?= json_encode(['labels' => $operationalTrends['volumeLabels'], 'values' => $operationalTrends['volumeDaily'], 'anomaly' => $operationalTrends['volumeAnomaly']]) ?>;</script>
        <?php endif; ?>
    </div>

    <!-- ============ FOOTER NOTICE ============ -->
    <div class="arp-footer-notice">
        <i class="bi bi-info-circle-fill"></i>
        <span>Stock requests and dispensing are scoped to you — Inventory Status is facility-wide. Trend chart covers the last 14 days.</span>
    </div>

</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= asset_url('/assets/js/chart-center-label.js') ?>"></script>

<script>
    window.ANALYTICS_REPORTS_DATA = <?= json_encode([
        'trend' => [
            'canvasId' => 'requestsOverTimeChart',
            'labels' => $trendLabels,
            'values' => $trendCounts,
        ],
        'donuts' => [
            [
                'canvasId' => 'requestsByStatusChart',
                'labels' => array_column($requestsByStatus, 'label'),
                'values' => array_column($requestsByStatus, 'value'),
                'colors' => array_column($requestsByStatus, 'color'),
            ],
            [
                'canvasId' => 'inventoryStatusChart',
                'labels' => array_column($inventoryStatusSummary, 'label'),
                'values' => array_column($inventoryStatusSummary, 'value'),
                'colors' => array_column($inventoryStatusSummary, 'color'),
            ],
        ],
    ]) ?>;
    // Stock Movement Summary / Inventory Expiry Monitor cards — same
    // real data shape subadmin/inventory_reports.php feeds the same
    // shared assets/js/inventory_movement_cards.js.
    window.INVENTORY_MOVEMENT_DATA = <?= json_encode([
        'stockMovementApiUrl' => BASE_URL . '/api/get-stock-movement.php',
        'expiryAllItems' => $irExpiryAllItems,
    ]) ?>;
</script>
<script src="<?= asset_url('/assets/js/inventory_movement_cards.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.initInventoryMovementCards === 'function') {
            window.initInventoryMovementCards(window.INVENTORY_MOVEMENT_DATA);
        }
    });
</script>
<script src="<?= asset_url('/assets/js/admin-sidebar-toggle.js') ?>"></script>
<script src="<?= asset_url('/assets/js/analytics_reports.js') ?>"></script>
<script>
// Operational Trends volume chart — real daily stock-request counts,
// set inline as window.OPERATIONAL_TRENDS (hc_operational_trends_overview()).
// Self-contained since assets/js/analytics_reports.js only supports one
// generic trend chart per page (already used by "My Stock Requests Over Time" above).
(function () {
    const canvas = document.getElementById('operationalVolumeChart');
    const op = window.OPERATIONAL_TRENDS;
    if (!canvas || !op || typeof Chart === 'undefined') return;
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border') || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted') || '#6B7280';
    const barColor = op.anomaly ? '#8B5CF6' : '#1A73E8';
    new Chart(canvas, {
        type: 'bar',
        data: { labels: op.labels, datasets: [{ data: op.values, backgroundColor: barColor, borderRadius: 4, maxBarThickness: 16 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxTicksLimit: 10 } },
            },
        },
    });
})();
</script>
</body>
</html>
